<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\EmailAccount;
use App\Entity\User;
use App\EventSubscriber\PortalAccountSyncStateSubscriber;
use App\Repository\RedirectionRepository;
use App\Service\OvhRedirectionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class AccountController extends AbstractController
{
    private const REDIRECT_COUNTDOWN_MAX = 120;

    #[Route('/compte', name: 'app_account')]
    public function index(
        Request $request,
        RedirectionRepository $redirectionRepository,
        OvhRedirectionService $ovhRedirectionService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $session = $request->getSession();

        if ($request->query->getBoolean('sync')) {
            $ovhRedirectionService->syncRedirectionsForUser($user);

            return $this->redirectToRoute('app_account');
        }

        if (!$session->get(PortalAccountSyncStateSubscriber::SESSION_PORTAL_REDIRECTIONS_SYNCED)) {
            $ovhRedirectionService->syncRedirectionsForUser($user);
            $session->set(PortalAccountSyncStateSubscriber::SESSION_PORTAL_REDIRECTIONS_SYNCED, true);
        }

        $redirectCountdown = $request->query->getInt('attente', 0);
        if ($redirectCountdown < 0 || $redirectCountdown > self::REDIRECT_COUNTDOWN_MAX) {
            $redirectCountdown = 0;
        }

        $accountsData = [];
        foreach ($user->getEmailAccounts() as $account) {
            if (!$account instanceof EmailAccount) {
                continue;
            }
            $accountsData[] = [
                'account' => $account,
                'redirections' => $redirectionRepository->findForAccountPortal($account),
            ];
        }

        return $this->render('account/index.html.twig', [
            'user' => $user,
            'accountsData' => $accountsData,
            'redirectCountdown' => $redirectCountdown,
            'redirectCountdownSyncUrl' => $this->generateUrl('app_account', ['sync' => 1]),
        ]);
    }

    #[Route('/compte/redirections/synchroniser', name: 'app_account_sync_redirections', methods: ['POST'])]
    public function syncRedirections(Request $request, OvhRedirectionService $ovhRedirectionService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('sync_redirections', $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Session expirée ou action invalide. Réessayez.');

            return $this->redirectToRoute('app_account');
        }

        $ovhRedirectionService->syncRedirectionsForUser($user);
        $this->addFlash('success', 'Liste des redirections mise à jour depuis OVH.');

        return $this->redirectToRoute('app_account');
    }
}
