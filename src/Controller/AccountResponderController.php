<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\EmailAccount;
use App\Entity\Responder;
use App\Entity\User;
use App\Form\ResponderAccountType;
use App\Repository\EmailAccountRepository;
use App\Repository\OrganizationSettingRepository;
use App\Repository\ResponderMessageTemplateRepository;
use App\Repository\ResponderRepository;
use App\Service\OvhResponderService;
use App\Service\ResponderTemplateInterpolator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
final class AccountResponderController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailAccountRepository $emailAccountRepository,
        private readonly ResponderRepository $responderRepository,
        private readonly ResponderMessageTemplateRepository $responderMessageTemplateRepository,
        private readonly OrganizationSettingRepository $organizationSettingRepository,
        private readonly OvhResponderService $ovhResponderService,
        private readonly ResponderTemplateInterpolator $responderTemplateInterpolator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/compte/boite/{accountId<\d+>}/repondeur/nouveau', name: 'app_account_responder_new', methods: ['GET', 'POST'])]
    public function new(Request $request, int $accountId): Response
    {
        $user = $this->requireUser();
        $account = $this->emailAccountRepository->find($accountId);
        if (null === $account || !$this->userOwnsEmailAccount($user, $account)) {
            throw $this->createNotFoundException('Compte introuvable.');
        }

        $existing = $this->responderRepository->findOneBy(['emailAccount' => $account]);
        if (null !== $existing) {
            return $this->redirectToRoute('app_account_responder_edit', ['id' => $existing->getId()]);
        }

        $responder = new Responder();
        $responder->setEmailAccount($account);
        $responder->setContent($this->defaultResponderContentForNewResponder());

        $form = $this->createForm(ResponderAccountType::class, $responder);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyResponderCopyDefaultsForPortal($responder);
            if (!$this->validateResponderDates($responder, $form)) {
                return $this->renderResponderForm($form, $account, 'Créer mon répondeur', null);
            }

            $this->entityManager->persist($responder);
            $this->entityManager->flush();

            if (!$this->ovhResponderService->createResponderOnOvh($responder)) {
                $this->entityManager->remove($responder);
                $this->entityManager->flush();
                $this->addFlash(
                    'danger',
                    'La création du répondeur sur OVH a échoué. Vérifiez les données ou réessayez plus tard.',
                );

                return $this->redirectToRoute('app_account_responder_new', ['accountId' => $accountId]);
            }

            $responder->setDateSync(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->addFlash('success', 'Répondeur créé sur OVH pour '.$account->getEmail().'.');

            return $this->redirectToRoute('app_account');
        }

        return $this->renderResponderForm($form, $account, 'Créer mon répondeur', null);
    }

    #[Route('/compte/repondeur/{id<\d+>}/modifier', name: 'app_account_responder_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $user = $this->requireUser();
        $responder = $this->responderRepository->find($id);
        if (null === $responder || !$this->userOwnsResponder($user, $responder)) {
            throw $this->createNotFoundException('Répondeur introuvable.');
        }

        $account = $responder->getEmailAccount();
        if (null === $account) {
            throw $this->createNotFoundException();
        }

        $this->alignResponderDateTimezonesForForm($responder);

        $form = $this->createForm(ResponderAccountType::class, $responder);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyResponderCopyDefaultsForPortal($responder);
            if (!$this->validateResponderDates($responder, $form)) {
                return $this->renderResponderForm($form, $account, 'Modifier mon répondeur', $responder);
            }

            $this->entityManager->flush();

            if (!$this->ovhResponderService->updateResponderOnOvh($responder)) {
                $this->addFlash(
                    'danger',
                    'La mise à jour sur OVH a échoué. Les modifications sont enregistrées localement ; réessayez plus tard.',
                );
            } else {
                $responder->setDateSync(new \DateTimeImmutable());
                $this->entityManager->flush();
                $this->addFlash('success', 'Répondeur mis à jour sur OVH pour '.$account->getEmail().'.');
            }

            return $this->redirectToRoute('app_account');
        }

        return $this->renderResponderForm($form, $account, 'Modifier mon répondeur', $responder);
    }

    #[Route('/compte/repondeur/{id<\d+>}/supprimer', name: 'app_account_responder_delete', methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $user = $this->requireUser();
        if (!$this->isCsrfTokenValid('delete_responder_'.$id, $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Session expirée ou action invalide. Réessayez.');

            return $this->redirectToRoute('app_account');
        }

        $responder = $this->responderRepository->find($id);
        if (null === $responder || !$this->userOwnsResponder($user, $responder)) {
            throw $this->createNotFoundException('Répondeur introuvable.');
        }

        $accountEmail = $responder->getEmailAccount()?->getEmail() ?? '';

        $ok = $this->ovhResponderService->deleteResponderOnOvh($responder);
        $this->entityManager->remove($responder);
        $this->entityManager->flush();

        if ($ok) {
            $this->addFlash('success', 'Répondeur supprimé sur OVH pour '.$accountEmail.'.');
        } else {
            $this->addFlash(
                'warning',
                'Répondeur supprimé dans l’application ; la suppression sur OVH a peut-être échoué. Vérifiez dans le manager OVH.',
            );
        }

        return $this->redirectToRoute('app_account');
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function userOwnsEmailAccount(User $user, EmailAccount $account): bool
    {
        $owner = $account->getUser();

        return null !== $owner && $owner->getId() === $user->getId();
    }

    private function userOwnsResponder(User $user, Responder $responder): bool
    {
        $account = $responder->getEmailAccount();

        return null !== $account && $this->userOwnsEmailAccount($user, $account);
    }

    /**
     * Évite l’erreur Symfony si Doctrine hydrate encore en UTC alors que le formulaire est en Europe/Paris.
     * Conserve le même instant ; l’heure affichée devient l’équivalent Paris.
     */
    private function alignResponderDateTimezonesForForm(Responder $responder): void
    {
        $paris = new \DateTimeZone('Europe/Paris');
        $from = $responder->getFromDate();
        if (null !== $from && 'Europe/Paris' !== $from->getTimezone()->getName()) {
            $responder->setFromDate($from->setTimezone($paris));
        }
        $to = $responder->getToDate();
        if (null !== $to && 'Europe/Paris' !== $to->getTimezone()->getName()) {
            $responder->setToDate($to->setTimezone($paris));
        }
    }

    /** Pas de copie depuis le portail utilisateur (champs absents du formulaire). */
    private function applyResponderCopyDefaultsForPortal(Responder $responder): void
    {
        $responder->setCopy(false);
        $responder->setCopyTo(null);
    }

    private function validateResponderDates(Responder $responder, FormInterface $form): bool
    {
        $from = $responder->getFromDate();
        $to = $responder->getToDate();

        if (null !== $to && null !== $from && $to < $from) {
            $form->addError(new FormError('La date de fin doit être postérieure à la date de début.'));

            return false;
        }

        return true;
    }

    private function renderResponderForm(FormInterface $form, EmailAccount $account, string $title, ?Responder $responder): Response
    {
        $presets = $this->getResponderPresetsForPortal();
        $agencyPhone = trim($this->organizationSettingRepository->getSingleton()->getAgencyPhone() ?? '');

        return $this->render('account/responder_form.html.twig', [
            'form' => $form,
            'account' => $account,
            'title' => $title,
            'responder' => $responder,
            'responderPresets' => $presets,
            'responderPresetAgencyPhone' => $agencyPhone,
            'responderPresetContentsJson' => json_encode(
                array_column($presets, 'content', 'code'),
                \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_AMP,
            ),
        ]);
    }

    private function defaultResponderContentForNewResponder(): string
    {
        $raw = null;
        $byCode = $this->responderMessageTemplateRepository->findActiveByCode('absence');
        if (null !== $byCode) {
            $raw = $byCode->getContent();
        }
        if (null === $raw) {
            $ordered = $this->responderMessageTemplateRepository->findActiveOrdered();
            $raw = isset($ordered[0]) ? $ordered[0]->getContent() : null;
        }
        if (null === $raw) {
            $raw = trim($this->translator->trans('responder.preset.absence'));
        }

        $phone = $this->organizationSettingRepository->getSingleton()->getAgencyPhone() ?? '';

        return $this->responderTemplateInterpolator->interpolate($raw, null, null, $phone);
    }

    /**
     * @return list<array{code: string, label: string, content: string}>
     */
    private function getResponderPresetsForPortal(): array
    {
        $fromDb = $this->responderMessageTemplateRepository->findActiveOrdered();
        if ([] !== $fromDb) {
            $out = [];
            foreach ($fromDb as $row) {
                $out[] = [
                    'code' => $row->getCode(),
                    'label' => $row->getLabel(),
                    'content' => trim($row->getContent()),
                ];
            }

            return $out;
        }

        return [
            [
                'code' => 'absence',
                'label' => 'Absence courte',
                'content' => trim($this->translator->trans('responder.preset.absence')),
            ],
            [
                'code' => 'conges',
                'label' => 'Congés',
                'content' => trim($this->translator->trans('responder.preset.conges')),
            ],
        ];
    }
}
