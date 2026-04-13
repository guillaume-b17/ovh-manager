<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\EmailAccount;
use App\Entity\Redirection;
use App\Entity\User;
use App\Form\RedirectionAccountType;
use App\Repository\EmailAccountRepository;
use App\Repository\RedirectionRepository;
use App\Service\OvhRedirectionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class AccountRedirectionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailAccountRepository $emailAccountRepository,
        private readonly RedirectionRepository $redirectionRepository,
        private readonly OvhRedirectionService $ovhRedirectionService,
    ) {
    }

    #[Route('/compte/boite/{accountId<\d+>}/redirection/nouvelle', name: 'app_account_redirection_new', methods: ['GET', 'POST'])]
    public function new(Request $request, int $accountId): Response
    {
        $user = $this->requireUser();
        $account = $this->emailAccountRepository->find($accountId);
        if (null === $account || !$this->userOwnsEmailAccount($user, $account)) {
            throw $this->createNotFoundException('Compte introuvable.');
        }

        $domain = $this->resolveDomainForAccount($account);
        if ('' === $domain) {
            $this->addFlash(
                'danger',
                'Le domaine de cette boîte est inconnu. Impossible de créer une redirection pour l’instant.',
            );

            return $this->redirectToRoute('app_account');
        }

        $redirection = new Redirection();
        $this->applyRedirectionFromAccount($redirection, $account);

        $form = $this->createForm(RedirectionAccountType::class, $redirection, [
            'allow_local_copy' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyRedirectionFromAccount($redirection, $account);
            $redirection->setSyncedAt(new \DateTimeImmutable());

            $taskId = $this->ovhRedirectionService->startCreation(
                $redirection->getDomain(),
                $redirection->getFromEmail(),
                $redirection->getToEmail(),
                $redirection->isLocalCopy(),
            );

            if (null === $taskId) {
                $this->addFlash(
                    'danger',
                    'OVH n’a pas accepté la création de la redirection. Réessayez plus tard ou contactez l’administrateur.',
                );

                return $this->renderRedirectionForm($form, $account, 'Nouvelle redirection', null);
            }

            $ovhId = $this->ovhRedirectionService->waitForRedirectionId(
                $redirection->getDomain(),
                $redirection->getFromEmail(),
                $redirection->getToEmail(),
                $taskId,
                20000,
            );

            if (null !== $ovhId) {
                $redirection->setOvhId($ovhId);
                $this->entityManager->persist($redirection);
                $this->entityManager->flush();
                $this->addFlash(
                    'success',
                    sprintf('Redirection créée : %s → %s.', $redirection->getFromEmail(), $redirection->getToEmail()),
                );

                return $this->redirectToRoute('app_account', ['attente' => 30]);
            }

            $this->addFlash(
                'warning',
                sprintf(
                    'La demande a été envoyée à OVH (tâche n°%s) mais la redirection n’est pas encore visible. '
                    .'Elle apparaîtra après synchronisation ou réessayez dans quelques instants.',
                    $taskId,
                ),
            );

            return $this->redirectToRoute('app_account', ['attente' => 30]);
        }

        return $this->renderRedirectionForm($form, $account, 'Nouvelle redirection', null);
    }

    #[Route('/compte/redirection/{id<\d+>}/modifier', name: 'app_account_redirection_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $user = $this->requireUser();
        $redirection = $this->redirectionRepository->find($id);
        if (null === $redirection || !$this->userOwnsRedirection($user, $redirection)) {
            throw $this->createNotFoundException('Redirection introuvable.');
        }

        $account = $redirection->getFromAccount()
            ?? $this->emailAccountRepository->findOneByEmailIgnoreCase($redirection->getFromEmail());
        if (null === $account || !$this->userOwnsEmailAccount($user, $account)) {
            throw $this->createNotFoundException('Compte source introuvable.');
        }

        if (null === $redirection->getFromAccount()) {
            $redirection->setFromAccount($account);
            $this->entityManager->flush();
        }

        $form = $this->createForm(RedirectionAccountType::class, $redirection, [
            'allow_local_copy' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyRedirectionFromAccount($redirection, $account);

            $ovhId = $redirection->getOvhId();
            if (null !== $ovhId && '' !== $ovhId) {
                $ok = $this->ovhRedirectionService->update(
                    $redirection->getDomain(),
                    $ovhId,
                    $redirection->getToEmail(),
                );
                if (!$ok) {
                    $form->addError(new FormError(
                        'La mise à jour sur OVH a échoué. Réessayez plus tard ou contactez l’administrateur.',
                    ));

                    return $this->renderRedirectionForm($form, $account, 'Modifier la redirection', $redirection);
                }
            }

            $redirection->setSyncedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->addFlash('success', 'Redirection mise à jour.');

            return $this->redirectToRoute('app_account');
        }

        return $this->renderRedirectionForm($form, $account, 'Modifier la redirection', $redirection);
    }

    #[Route('/compte/redirection/{id<\d+>}/supprimer', name: 'app_account_redirection_delete', methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $user = $this->requireUser();
        if (!$this->isCsrfTokenValid('delete_redirection_'.$id, $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Session expirée ou action invalide. Réessayez.');

            return $this->redirectToRoute('app_account');
        }

        $redirection = $this->redirectionRepository->find($id);
        if (null === $redirection || !$this->userOwnsRedirection($user, $redirection)) {
            throw $this->createNotFoundException('Redirection introuvable.');
        }

        $label = $redirection->getFromEmail().' → '.$redirection->getToEmail();
        $this->ovhRedirectionService->remove($redirection);
        $this->addFlash('success', 'Redirection supprimée : '.$label.'.');

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

    private function userOwnsRedirection(User $user, Redirection $redirection): bool
    {
        $account = $redirection->getFromAccount();
        if (null !== $account) {
            return $this->userOwnsEmailAccount($user, $account);
        }

        $from = strtolower(trim($redirection->getFromEmail()));
        foreach ($user->getEmailAccounts() as $acc) {
            if (!$acc instanceof EmailAccount) {
                continue;
            }
            $mail = $acc->getEmail();
            if (null !== $mail && strtolower(trim($mail)) === $from) {
                return true;
            }
        }

        return false;
    }

    private function resolveDomainForAccount(EmailAccount $account): string
    {
        $domain = $account->getDomain();
        if (null !== $domain && '' !== $domain) {
            return $domain;
        }

        $email = $account->getEmail();
        if (null !== $email && str_contains($email, '@')) {
            return substr(strrchr($email, '@'), 1) ?: '';
        }

        return '';
    }

    private function applyRedirectionFromAccount(Redirection $redirection, EmailAccount $account): void
    {
        $email = $account->getEmail();
        if (null === $email || '' === $email) {
            return;
        }

        $redirection->setFromAccount($account);
        $redirection->setFromEmail($email);
        $domain = $this->resolveDomainForAccount($account);
        if ('' !== $domain) {
            $redirection->setDomain($domain);
        }
    }

    private function renderRedirectionForm(
        FormInterface $form,
        EmailAccount $account,
        string $title,
        ?Redirection $redirection,
    ): Response {
        return $this->render('account/redirection_form.html.twig', [
            'form' => $form,
            'account' => $account,
            'title' => $title,
            'redirection' => $redirection,
        ]);
    }
}
