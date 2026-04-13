<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\EmailOtpAuthenticator;
use App\Service\EmailLoginChallengeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EmailLoginController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login', methods: ['GET'])]
    public function requestCodeForm(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $request->getSession()->remove(EmailOtpAuthenticator::SESSION_PENDING_EMAIL);

        return $this->render('security/login_email.html.twig');
    }

    #[Route(path: '/login/code', name: 'app_login_send_code', methods: ['GET', 'POST'])]
    public function sendCode(Request $request, EmailLoginChallengeService $challengeService): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('email_login_request', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('danger', 'Session expirée ou formulaire invalide. Réessayez.');

            return $this->redirectToRoute('app_login');
        }

        $emailRaw = $request->request->getString('email');
        if (!filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('danger', 'Adresse e-mail invalide.');

            return $this->redirectToRoute('app_login');
        }

        $email = EmailLoginChallengeService::normalizeEmail($emailRaw);

        try {
            $sent = $challengeService->requestNewCode($email);
        } catch (\RuntimeException $e) {
            $this->addFlash('warning', $e->getMessage());

            return $this->redirectToRoute('app_login');
        }

        if (!$sent) {
            $this->addFlash(
                'info',
                'Si cet e-mail est autorisé, vous recevrez un message contenant un code sous peu.',
            );

            return $this->redirectToRoute('app_login');
        }

        $request->getSession()->set(EmailOtpAuthenticator::SESSION_PENDING_EMAIL, $email);
        $this->addFlash('success', 'Un code de vérification vous a été envoyé par e-mail.');

        return $this->redirectToRoute('app_login_verify');
    }

    #[Route(path: '/login/verify', name: 'app_login_verify', methods: ['GET'])]
    public function verifyCodeForm(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $session = $request->getSession();
        if (!$session->has(EmailOtpAuthenticator::SESSION_PENDING_EMAIL)) {
            $this->addFlash('warning', 'Commencez par demander un code sur la page de connexion.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/login_verify.html.twig', [
            'pendingEmail' => (string) $session->get(EmailOtpAuthenticator::SESSION_PENDING_EMAIL),
        ]);
    }

    /**
     * @internal La soumission est traitée par {@see \App\Security\EmailOtpAuthenticator} avant ce point.
     */
    #[Route(path: '/login/verify', name: 'app_login_verify_submit', methods: ['POST'])]
    public function verifyCodeSubmit(): never
    {
        throw new \LogicException('La soumission du code de connexion doit être interceptée par le pare-feu de sécurité.');
    }
}
