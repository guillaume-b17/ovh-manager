<?php

namespace App\Service;

use Ovh\Api;

class OvhEmailService
{
    public function __construct(private OvhClientService $ovh)
    {
    }

    public function listDomains(): array
    {
        // Récupère tous les domaines email OVH
        $allDomains = $this->ovh->getClient()->get('/email/domain');

        // ✅ Filtre uniquement ceux qu’on souhaite synchroniser
        $allowed = ['b17.fr', 'izardcom.fr'];

        return array_values(array_filter($allDomains, fn($d) => in_array($d, $allowed, true)));
    }


    public function listAccounts(string $domain): array
    {
        return $this->ovh->getClient()->get("/email/domain/$domain/account");
    }

    public function getAccountDetails(string $domain, string $account): array
    {
        return $this->ovh->getClient()->get("/email/domain/$domain/account/$account");
    }

    public function getAccountUsage(string $domain, string $account): array
    {
        return $this->ovh->getClient()->get("/email/domain/$domain/account/$account/usage");
    }



}
