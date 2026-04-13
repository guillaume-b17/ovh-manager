<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Détermine quels e-mails ont le rôle administrateur (accès EasyAdmin).
 */
final class AdminEmailService
{
    /**
     * @param list<string> $adminEmails
     */
    public function __construct(
        #[Autowire(param: 'app.admin_emails')]
        private readonly array $adminEmails,
    ) {
    }

    public function isAdminEmail(string $email): bool
    {
        $email = EmailLoginChallengeService::normalizeEmail($email);
        foreach ($this->adminEmails as $adminEmail) {
            if (EmailLoginChallengeService::normalizeEmail((string) $adminEmail) === $email) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rôles stockés en base (ROLE_USER est toujours ajouté par {@see User::getRoles()}).
     *
     * @return list<string>
     */
    public function persistedRolesForEmail(string $email): array
    {
        return $this->isAdminEmail($email) ? ['ROLE_ADMIN'] : [];
    }

    /**
     * Aligne les rôles persistés sur la liste des administrateurs.
     *
     * @return bool true si la base doit être flushée
     */
    public function syncStoredRolesIfNeeded(User $user): bool
    {
        $email = EmailLoginChallengeService::normalizeEmail((string) $user->getEmail());
        $shouldBeAdmin = $this->isAdminEmail($email);
        $isAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);

        if ($shouldBeAdmin === $isAdmin) {
            return false;
        }

        $user->setRoles($shouldBeAdmin ? ['ROLE_ADMIN'] : []);

        return true;
    }
}
