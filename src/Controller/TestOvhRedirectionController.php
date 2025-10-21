<?php

namespace App\Controller;

use App\Service\OvhRedirectionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TestOvhRedirectionController extends AbstractController
{
    public function __construct(
        private OvhRedirectionService $ovhService
    ) {}

    #[Route('/test/ovh/redirections/{domain}', name: 'test_ovh_redirections')]
    public function testList(string $domain): Response
    {
        try {
            $ids = $this->ovhService->listIds($domain);

            if (empty($ids)) {
                return new Response("Aucune redirection trouvée pour le domaine '{$domain}'.");
            }

            $firstId = $ids[0];
            $detail = $this->ovhService->getById($domain, (string)$firstId);

            $output = [
                'count' => count($ids),
                'first_id' => $firstId,
                'first_detail' => $detail,
            ];

            // ✅ Correction ici :
            return $this->json($output, 200, [], ['json_encode_options' => JSON_PRETTY_PRINT]);

        } catch (\Throwable $e) {
            return new Response("Erreur OVH : " . $e->getMessage(), 500);
        }
    }

}
