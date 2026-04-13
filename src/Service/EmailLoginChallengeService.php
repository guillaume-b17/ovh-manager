<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EmailLoginChallenge;
use App\Repository\EmailAccountRepository;
use App\Repository\EmailLoginChallengeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Demande et validation des codes de connexion envoyés par e-mail.
 */
class EmailLoginChallengeService
{
    private const CODE_TTL_MINUTES = 10;

    private const MAX_ATTEMPTS_PER_CHALLENGE = 5;

    private const MAX_NEW_CODES_PER_WINDOW = 5;

    private const RATE_LIMIT_WINDOW_MINUTES = 15;

    public function __construct(
        private readonly EmailLoginChallengeRepository $challengeRepository,
        private readonly UserRepository $userRepository,
        private readonly EmailAccountRepository $emailAccountRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.environment%')]
        private readonly string $appEnvironment,
        #[Autowire('%env(EMAIL_LOGIN_DEV_CODE)%')]
        private readonly string $devLoginBypassCode,
    ) {
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public function isEmailEligible(string $email): bool
    {
        $email = self::normalizeEmail($email);
        if ('' === $email) {
            return false;
        }

        if (null !== $this->userRepository->findOneBy(['email' => $email])) {
            return true;
        }

        return null !== $this->emailAccountRepository->findOneBy(['email' => $email]);
    }

    /**
     * @throws \RuntimeException limite de débit atteinte
     */
    public function requestNewCode(string $email): bool
    {
        $email = self::normalizeEmail($email);
        if (!$this->isEmailEligible($email)) {
            return false;
        }

        $now = new \DateTimeImmutable();
        $since = $now->modify(sprintf('-%d minutes', self::RATE_LIMIT_WINDOW_MINUTES));
        if ($this->challengeRepository->countRecentForEmail($email, $since) >= self::MAX_NEW_CODES_PER_WINDOW) {
            throw new \RuntimeException('Trop de demandes de code pour cet e-mail. Réessayez plus tard.');
        }

        $this->invalidatePendingForEmail($email);

        $plainCode = $this->generateNumericCode();
        $hash = password_hash($plainCode, PASSWORD_DEFAULT);

        $challenge = new EmailLoginChallenge(
            $email,
            $hash,
            $now,
            $now->modify(sprintf('+%d minutes', self::CODE_TTL_MINUTES)),
        );

        $this->entityManager->persist($challenge);
        $this->entityManager->flush();

        try {
            $this->sendCodeEmail($email, $plainCode);
        } catch (\Throwable $e) {
            $this->entityManager->remove($challenge);
            $this->entityManager->flush();

            throw $e;
        }

        return true;
    }

    public function verifyAndConsumeCode(string $email, string $plainCode): bool
    {
        $email = self::normalizeEmail($email);
        $plainCode = trim($plainCode);
        if ('' === $email || '' === $plainCode) {
            return false;
        }

        $devCode = trim($this->devLoginBypassCode);
        if (
            'dev' === $this->appEnvironment
            && '' !== $devCode
            && hash_equals($devCode, $plainCode)
            && $this->isEmailEligible($email)
        ) {
            $this->logger->info('Connexion : code de contournement dev accepté.', ['email' => $email]);

            return true;
        }

        $now = new \DateTimeImmutable();
        $challenge = $this->challengeRepository->findLatestActiveForEmail($email, $now);
        if (null === $challenge) {
            return false;
        }

        if ($challenge->getAttempts() >= self::MAX_ATTEMPTS_PER_CHALLENGE) {
            return false;
        }

        if (!password_verify($plainCode, $challenge->getCodeHash())) {
            $challenge->incrementAttempts();
            $this->entityManager->flush();

            return false;
        }

        $challenge->setConsumedAt($now);
        $this->entityManager->flush();

        return true;
    }

    private function invalidatePendingForEmail(string $email): void
    {
        $now = new \DateTimeImmutable();
        foreach ($this->challengeRepository->findPendingForEmail($email) as $pending) {
            $pending->setConsumedAt($now);
        }
        $this->entityManager->flush();
    }

    private function generateNumericCode(): string
    {
        return (string) random_int(100_000, 999_999);
    }

    private function sendCodeEmail(string $to, string $code): void
    {
        $message = (new Email())
            ->from(new Address('noreply@localhost', 'OVH Manager'))
            ->to($to)
            ->subject('Votre code de connexion')
            ->text(
                "Bonjour,\n\n"
                ."Voici votre code de connexion : {$code}\n"
                .'Il est valable '.self::CODE_TTL_MINUTES." minutes.\n\n"
                ."Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.\n",
            );

        try {
            $this->mailer->send($message);
        } catch (\Throwable $e) {
            $this->logger->error('Échec envoi e-mail code connexion : '.$e->getMessage());

            throw new \RuntimeException("Impossible d'envoyer l'e-mail contenant le code.", 0, $e);
        }
    }
}
