<?php

namespace App\Controller;

use App\Service\OvhClientService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/ovh/test', name: 'ovh_test_')]
class OvhTestController extends AbstractController
{
    public function __construct(private OvhClientService $ovh)
    {
    }

    #[Route('/usage/{domain}/{accountName}', name: 'usage', methods: ['GET'])]
    public function testUsage(string $domain, string $accountName): JsonResponse
    {
        try {
            // 🔹 Appel signé via le client OVH officiel
            $usage = $this->ovh->getClient()->get("/email/domain/$domain/account/$accountName/usage");

            // 🔍 Formatage lisible
            return $this->json([
                'domain' => $domain,
                'account' => $accountName,
                'usage' => $usage,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'domain' => $domain,
                'account' => $accountName,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/account/{domain}/{accountName}', name: 'account', methods: ['GET'])]
    public function testAccount(string $domain, string $accountName): JsonResponse
    {
        try {
            $details = $this->ovh->getClient()->get("/email/domain/$domain/account/$accountName");

            return $this->json([
                'domain' => $domain,
                'account' => $accountName,
                'details' => $details,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'domain' => $domain,
                'account' => $accountName,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
