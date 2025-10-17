<?php
// src/Controller/OvhEmailController.php

namespace App\Controller;

use App\Entity\EmailAccount;
use App\Service\OvhClientService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/ovh/email', name: 'ovh_email_')]
class OvhEmailController extends AbstractController
{
    public function __construct(
        private readonly OvhClientService $ovh,
        private readonly EntityManagerInterface $em,
    ) {}

    /** Domaines autorisés pour la synchro */
    private const ALLOWED_DOMAINS = ['b17.fr', 'izardcom.fr'];

    // --------------------------
    // Helpers internes
    // --------------------------

    /** Retourne une string BIGINT (ou null) sans jamais renvoyer '' */
    private function asBigintString(mixed $value): ?string
    {
        if ($value === null) return null;
        if ($value === '')   return null;
        if (is_numeric($value)) return (string)$value;
        return null;
    }

    /** True si domaine autorisé */
    private function isAllowedDomain(string $domain): bool
    {
        return in_array($domain, self::ALLOWED_DOMAINS, true);
    }

    // --------------------------
    // Petites méthodes API OVH
    // --------------------------

    /** Liste les comptes d’un domaine : GET /email/domain/{domain}/account */
    #[Route('/{domain}/accounts', name: 'list_accounts', methods: ['GET'])]
    public function listAccounts(string $domain): Response
    {
        if (!$this->isAllowedDomain($domain)) {
            return $this->json(['error' => 'Domaine non autorisé'], 400);
        }

        try {
            $accounts = $this->ovh->getClient()->get("/email/domain/$domain/account");
            return $this->json($accounts);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /** Détails d’un compte : GET /email/domain/{domain}/account/{accountName} */
    #[Route('/{domain}/account/{accountName}', name: 'account_details', methods: ['GET'])]
    public function accountDetails(string $domain, string $accountName): Response
    {
        if (!$this->isAllowedDomain($domain)) {
            return $this->json(['error' => 'Domaine non autorisé'], 400);
        }

        try {
            $details = $this->ovh->getClient()->get("/email/domain/$domain/account/$accountName");
            return $this->json($details);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /** Usage d’un compte : GET /email/domain/{domain}/account/{accountName}/usage */
    #[Route('/{domain}/account/{accountName}/usage', name: 'account_usage', methods: ['GET'])]
    public function accountUsage(string $domain, string $accountName): Response
    {
        if (!$this->isAllowedDomain($domain)) {
            return $this->json(['error' => 'Domaine non autorisé'], 400);
        }

        try {
            $usage = $this->ovh->getClient()->get("/email/domain/$domain/account/$accountName/usage");
            return $this->json($usage);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // --------------------------
    // Synchronisation DB
    // --------------------------

    /** Synchronise tous les comptes des domaines autorisés */
    #[Route('/sync', name: 'sync', methods: ['GET'])]
    public function sync(): Response
    {
        $synced = 0;
        $errors = [];

        foreach (self::ALLOWED_DOMAINS as $domain) {
            try {
                $accounts = $this->ovh->getClient()->get("/email/domain/$domain/account");
            } catch (\Throwable $e) {
                $errors[] = "[$domain] Erreur lors de la récupération des comptes : " . $e->getMessage();
                continue;
            }

            foreach ($accounts as $accountName) {
                try {
                    // 1️⃣ Détails statiques
                    $details = $this->ovh->getClient()->get("/email/domain/$domain/account/$accountName");

                    // 2️⃣ Détails dynamiques (usage)
                    $usage = null;
                    try {
                        $usage = $this->ovh->getClient()->get("/email/domain/$domain/account/$accountName/usage");
                    } catch (\Throwable $ignored) {
                        // Pas bloquant, MXPlan peut ne pas avoir /usage
                    }

                    // 3️⃣ Recherche ou création de l'entité
                    $entity = $this->em->getRepository(EmailAccount::class)
                        ->findOneBy(['domain' => $domain, 'accountName' => $accountName])
                        ?? new EmailAccount();

                    // 4️⃣ Données principales
                    $entity->setDomain($domain);
                    $entity->setAccountName($accountName);
                    $entity->setEmail($details['email'] ?? "$accountName@$domain");
                    $entity->setDisplayName($details['displayName'] ?? null);
                    //$entity->setQuota($this->asBigintString($details['quote'] ?? null));
                    $entity->setSize($this->asBigintString($details['size'] ?? null));

                    // 5️⃣ Données "usage" (sécurisées)
                    $entity->setUsageQuota(
                        isset($usage['quota']) && is_numeric($usage['quota'])
                            ? (string)$usage['quota']
                            : null
                    );
                    $entity->setUsageEmailCount(
                        isset($usage['emailCount']) && is_numeric($usage['emailCount'])
                            ? (int)$usage['emailCount']
                            : null
                    );
                    $entity->setUsageDate(
                        isset($usage['date']) && !empty($usage['date'])
                            ? new \DateTimeImmutable($usage['date'])
                            : null
                    );

                    // 6️⃣ Date de synchro
                    $entity->setDateSync(new \DateTimeImmutable());

                    // 7️⃣ Persistance
                    $this->em->persist($entity);
                    $synced++;
                } catch (\Throwable $e) {
                    $errors[] = "[$domain:$accountName] " . $e->getMessage();
                }
            }
        }

        // 8️⃣ Sauvegarde
        $this->em->flush();

        // 9️⃣ Feedback utilisateur
        if ($errors) {
            $this->addFlash('warning', "⚠️ Synchronisation partielle : $synced comptes mis à jour (" . count($errors) . " erreurs)");
            return $this->json(['synced' => $synced, 'errors' => $errors], 207);
        }

        $this->addFlash('success', "✅ Synchronisation réussie ($synced comptes)");
        return $this->redirectToRoute('admin');
    }

}
