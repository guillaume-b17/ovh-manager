<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Service\EmailLoginChallengeService;
use App\Service\EmailOtpUserProvisioner;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class EmailOtpAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public const SESSION_PENDING_EMAIL = 'email_login_pending_email';

    public function __construct(
        private readonly EmailLoginChallengeService $challengeService,
        private readonly EmailOtpUserProvisioner $userProvisioner,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->isMethod('POST')
            && 'app_login_verify_submit' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(
            'email_login_verify',
            $request->request->getString('_csrf_token'),
        ))) {
            throw new InvalidCsrfTokenException();
        }

        $session = $request->getSession();
        $email = EmailLoginChallengeService::normalizeEmail((string) $session->get(self::SESSION_PENDING_EMAIL));
        if ('' === $email) {
            throw new CustomUserMessageAuthenticationException(
                'Session de connexion expirée. Demandez un nouveau code depuis la page de connexion.',
            );
        }

        $code = preg_replace('/\s+/', '', $request->request->getString('code'));
        if ('' === $code) {
            throw new CustomUserMessageAuthenticationException('Veuillez saisir le code reçu par e-mail.');
        }

        if (!$this->challengeService->verifyAndConsumeCode($email, $code)) {
            throw new CustomUserMessageAuthenticationException('Code invalide ou expiré.');
        }

        try {
            $user = $this->userProvisioner->getOrCreateUserForEmail($email);
        } catch (UserNotFoundException) {
            throw new CustomUserMessageAuthenticationException(
                'Aucun compte ne correspond à cette adresse e-mail.',
            );
        }

        $badges = [];
        if ($request->request->getBoolean('_remember_me')) {
            $badges[] = new RememberMeBadge();
        }

        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier(), static fn () => $user),
            $badges,
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $session = $request->getSession();
        $session->migrate(true);
        $session->remove(self::SESSION_PENDING_EMAIL);

        $user = $token->getUser();
        $target = 'app_account';
        if ($user instanceof User && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $target = 'admin';
        }

        return new RedirectResponse($this->urlGenerator->generate($target));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = match (true) {
            $exception instanceof CustomUserMessageAuthenticationException => $exception->getMessage(),
            $exception instanceof InvalidCsrfTokenException => 'Session expirée ou formulaire invalide. Réessayez.',
            default => 'La connexion a échoué.',
        };

        $request->getSession()->getFlashBag()->add('danger', $message);

        return new RedirectResponse($this->urlGenerator->generate('app_login_verify'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
