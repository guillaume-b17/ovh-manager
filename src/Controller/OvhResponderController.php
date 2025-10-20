<?php

namespace App\Controller;

use App\Entity\EmailAccount;
use App\Service\OvhResponderService;
use App\Service\OvhClientService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/ovh/responder', name: 'ovh_responder_')]
class OvhResponderController extends AbstractController
{
    public function __construct(
        private readonly OvhClientService $ovh,
        private readonly OvhResponderService $responderService,
        private readonly EntityManagerInterface $em
    ) {}

    // 🔹 GET : Récupérer le répondeur d’un compte OVH
    #[Route('/get/{domain}/{account}', name: 'get', methods: ['GET'])]
    public function getResponder(string $domain, string $account): JsonResponse
    {
        try {
            $response = $this->ovh->getClient()->get("/email/domain/$domain/responder/$account");
            return $this->json($response);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // 🔹 POST : Créer un répondeur
    #[Route('/create/{domain}', name: 'create', methods: ['POST'])]
    public function createResponder(Request $request, string $domain): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!$data || empty($data['account'])) {
            return $this->json(['error' => 'Missing account field'], 400);
        }

        try {
            $response = $this->ovh->getClient()->post("/email/domain/$domain/responder", $data);
            return $this->json(['status' => 'created', 'response' => $response]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // 🔹 PUT : Mettre à jour un répondeur
    #[Route('/update/{domain}/{account}', name: 'update', methods: ['PUT'])]
    public function updateResponder(Request $request, string $domain, string $account): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $response = $this->ovh->getClient()->put("/email/domain/$domain/responder/$account", $data);
            return $this->json(['status' => 'updated', 'response' => $response]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // 🔹 DELETE : Supprimer un répondeur
    #[Route('/delete/{domain}/{account}', name: 'delete', methods: ['DELETE'])]
    public function deleteResponder(string $domain, string $account): JsonResponse
    {
        try {
            $response = $this->ovh->getClient()->delete("/email/domain/$domain/responder/$account");
            return $this->json(['status' => 'deleted', 'response' => $response]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // 🔹 SYNC : Synchroniser un seul répondeur lié à un compte email existant
    #[Route('/sync/{emailAccountId}', name: 'sync_one', methods: ['GET'])]
    public function syncOneResponder(int $emailAccountId): JsonResponse
    {
        $account = $this->em->getRepository(EmailAccount::class)->find($emailAccountId);
        if (!$account) {
            return $this->json(['error' => 'EmailAccount not found'], 404);
        }

        $responder = $this->responderService->syncResponder($account);
        if (!$responder) {
            return $this->json(['status' => 'no responder for this account']);
        }

        return $this->json([
            'status' => 'synced',
            'account' => $account->getEmail(),
            'content' => $responder->getContent(),
            'from' => $responder->getFromDate()?->format('Y-m-d H:i'),
            'to' => $responder->getToDate()?->format('Y-m-d H:i'),
        ]);
    }

    // 🔹 SYNC-ALL : Synchroniser tous les répondeurs
    #[Route('/sync-all', name: 'sync_all', methods: ['GET'])]
    public function syncAllResponders(): JsonResponse
    {
        $result = $this->responderService->syncAllResponders();

        return $this->json([
            'status' => 'done',
            'synced' => $result['synced'],
            'errors' => $result['errors'],
        ]);
    }
}
