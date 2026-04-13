<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\EmailAccountRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

/**
 * Associe un utilisateur Symfony à une adresse e-mail connue (User ou compte OVH synchronisé).
 */
class EmailOtpUserProvisioner
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EmailAccountRepository $emailAccountRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly AdminEmailService $adminEmailService,
    ) {
    }

    public function getOrCreateUserForEmail(string $email): User
    {
        $email = EmailLoginChallengeService::normalizeEmail($email);

        $existing = $this->userRepository->findOneBy(['email' => $email]);
        if (null !== $existing) {
            return $this->finalizeUser($existing);
        }

        $account = $this->emailAccountRepository->findOneBy(['email' => $email]);
        if (null === $account) {
            throw new UserNotFoundException();
        }

        if (null !== $account->getUser()) {
            return $this->finalizeUser($account->getUser());
        }

        $user = new User();
        $user->setEmail($email);
        $user->setRoles($this->adminEmailService->persistedRolesForEmail($email));
        $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));

        $this->entityManager->wrapInTransaction(function () use ($user, $account): void {
            $this->entityManager->persist($user);
            $account->setUser($user);
            $this->entityManager->flush();
        });

        return $this->finalizeUser($user);
    }

    private function finalizeUser(User $user): User
    {
        if ($this->adminEmailService->syncStoredRolesIfNeeded($user)) {
            $this->entityManager->flush();
        }

        return $user;
    }
}
