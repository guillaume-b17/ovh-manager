<?php

namespace App\Service;

use App\Entity\Redirection;
use App\Repository\RedirectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Ovh\Api;

class OvhRedirectionService
{
    private Api $client;

    public function __construct(
        OvhClientService $ovhClientService,
        private RedirectionRepository $repository,
        private EntityManagerInterface $em,
        private LoggerInterface $logger
    ) {
        $this->client = $ovhClientService->getClient();
    }

    // ============================================================
    // 🔹 API BASICS
    // ============================================================

    /** Retourne les IDs de redirections pour un domaine donné */
    public function listIds(string $domain, ?string $from = null, ?string $to = null): array
    {
        try {
            // ⚙️ Gestion correcte des paramètres facultatifs
            if ($from || $to) {
                $params = array_filter(['from' => $from, 'to' => $to]);
                $ids = $this->client->get("/email/domain/{$domain}/redirection", $params);
            } else {
                $ids = $this->client->get("/email/domain/{$domain}/redirection");
            }

            // 🧾 Log de debug
            $this->logger->info("[OVH] listIds($domain) → " . json_encode($ids));

            return is_array($ids) ? $ids : [];
        } catch (\Throwable $e) {
            $this->logger->error("[OVH] listIds {$domain} : {$e->getMessage()}");
            return [];
        }
    }



    /** Récupère les détails d’une redirection */
    public function getById(string $domain, string $id): ?array
    {
        try {
            $data = $this->client->get("/email/domain/{$domain}/redirection/{$id}");
            $this->logger->info("[OVH] getById($domain, $id) → " . json_encode($data));
            return $data;
        } catch (\Throwable $e) {
            $this->logger->error("[OVH] getById {$domain}/{$id} : {$e->getMessage()}");
            return null;
        }
    }


    /** Crée une nouvelle redirection sur OVH */
    public function create(string $domain, string $from, string $to, bool $localCopy = false): ?array
    {
        try {
            // 1️⃣ Envoi de la demande
            $task = $this->client->getClient()->post(
                "/email/domain/{$domain}/redirection",
                [
                    'from' => $from,
                    'to' => $to,
                    'localCopy' => $localCopy,
                ]
            );

            $this->logger->info("[OVH] Tâche création redirection → " . json_encode($task));

            $taskId = $task['id'] ?? null;
            if (!$taskId) {
                throw new \RuntimeException('Aucun taskId retourné par OVH.');
            }

            // 2️⃣ Attente de la fin de la tâche
            $status = null;
            for ($i = 0; $i < 10; $i++) {
                usleep(1000000); // attendre 1 seconde
                $taskInfo = $this->client->getClient()->get("/email/domain/{$domain}/task/{$taskId}");
                $status = $taskInfo['status'] ?? null;

                $this->logger->info("[OVH] Statut de la tâche #{$taskId} : {$status}");

                if ($status === 'done') {
                    break;
                }
            }

            if ($status !== 'done') {
                throw new \RuntimeException("La tâche de création OVH n’a pas abouti (status={$status}).");
            }

            // 3️⃣ Une fois la tâche terminée, récupération de la redirection
            $ids = $this->client->getClient()->get("/email/domain/{$domain}/redirection", [
                'from' => $from,
                'to'   => $to,
            ]);

            $this->logger->info("[OVH] Redirections trouvées après tâche #{$taskId} → " . json_encode($ids));

            if (is_array($ids) && count($ids) > 0) {
                $id = end($ids);
                $this->logger->info("[OVH] Redirection finale créée avec ID {$id}");
                return ['id' => (string)$id];
            }

            throw new \RuntimeException('Redirection non trouvée après création.');

        } catch (\Throwable $e) {
            $this->logger->error("[OVH] Erreur création redirection: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Start creation on OVH and return the taskId (not the redirection id).
     * Never throws; returns null if the POST fails for any reason.
     */
    /**
     * Lance la création d'une redirection sur OVH et retourne le taskId.
     * Si aucune tâche n'est renvoyée, retourne null.
     */
    /**
     * Lance la création d'une redirection sur OVH et retourne le taskId.
     * Si aucune tâche n'est renvoyée, retourne null.
     */
    public function startCreation(string $domain, string $from, string $to, bool $localCopy = false): ?string
    {
        try {
            // 1️⃣ Envoi direct via le client OVH (déjà une instance de Ovh\Api)
            $task = $this->client->post(
                "/email/domain/{$domain}/redirection",
                [
                    'from' => $from,
                    'to' => $to,
                    'localCopy' => $localCopy,
                ]
            );

            // 2️⃣ Log brut pour voir la réponse complète
            $this->logger->info("[OVH] startCreation brut → " . print_r($task, true));

            // 3️⃣ Normalisation du retour
            $taskId = null;

            if (is_array($task)) {
                $taskId = $task['id'] ?? ($task[0]['id'] ?? null);
            } elseif (is_object($task)) {
                $taskId = $task->id ?? null;
            } elseif (is_scalar($task)) {
                $taskId = (string) $task;
            }

            // 4️⃣ Log final
            $this->logger->info("[OVH] startCreation normalisé taskId=" . ($taskId ?? 'null'));

            return $taskId ?: null;
        } catch (\Throwable $e) {
            $this->logger->error("[OVH] Erreur création redirection: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Attend que la tâche OVH soit terminée, puis récupère l'ID réel de redirection.
     * Retourne l'ID OVH ou null si rien trouvé après le délai.
     */
    public function waitForRedirectionId(string $domain, string $from, string $to, string $taskId, int $timeoutMs = 8000): ?string
    {
        $elapsed = 0;
        $interval = 1000000; // 1 seconde entre chaque check
        $maxAttempts = (int) ceil($timeoutMs / 1000);

        try {
            for ($i = 0; $i < $maxAttempts; $i++) {
                // Vérifie le statut de la tâche
                $taskInfo = $this->client->get("/email/domain/{$domain}/task/{$taskId}");
                $status = is_array($taskInfo) ? ($taskInfo['status'] ?? null) : (is_object($taskInfo) ? ($taskInfo->status ?? null) : null);

                $this->logger->info("[OVH] Statut tâche {$taskId}: {$status}");

                if ($status === 'done') {
                    // Une fois terminée, tente de retrouver la redirection
                    $ids = $this->client->get("/email/domain/{$domain}/redirection", [
                        'from' => $from,
                        'to' => $to,
                    ]);

                    $this->logger->info("[OVH] Redirections trouvées après tâche #{$taskId} → " . print_r($ids, true));

                    if (is_array($ids) && count($ids) > 0) {
                        $id = (string) end($ids);
                        $this->logger->info("[OVH] Redirection finale créée avec ID {$id}");
                        return $id;
                    }

                    $this->logger->warning("[OVH] Tâche {$taskId} terminée, mais aucune redirection trouvée pour {$from} → {$to}");
                    return null;
                }

                usleep($interval);
                $elapsed += $interval / 1000;
            }

            $this->logger->warning("[OVH] Timeout atteint (aucune redirection trouvée pour {$from} → {$to})");
            return null;
        } catch (\Throwable $e) {
            $this->logger->error("[OVH] waitForRedirectionId error: {$e->getMessage()}");
            return null;
        }
    }




    /** Met à jour une redirection existante */
    public function update(string $domain, string $id, string $to): bool
    {
        try {
            $this->client->post("/email/domain/{$domain}/redirection/{$id}/changeRedirection", [
                'to' => $to,
            ]);
            return true;
        } catch (\Throwable $e) {
            $this->logger->error("[OVH] update redirection {$domain}/{$id} : {$e->getMessage()}");
            return false;
        }
    }

    /** Supprime une redirection */
    public function delete(string $domain, string $id): bool
    {
        try {
            $this->client->delete("/email/domain/{$domain}/redirection/{$id}");
            return true;
        } catch (\Throwable $e) {
            $this->logger->error("[OVH] delete redirection {$domain}/{$id} : {$e->getMessage()}");
            return false;
        }
    }

    // ============================================================
    // 🔹 LOGIQUE MÉTIER (SYNCHRO)
    // ============================================================

    /** Synchronise toutes les redirections d’un domaine */
    public function syncDomain(string $domain): int
    {
        $count = 0;
        $ids = $this->listIds($domain);

        foreach ($ids as $id) {
            $data = $this->getById($domain, $id);
            if (!$data) continue;

            $entity = $this->repository->findOneBy(['ovhId' => (string)$id]) ?? new Redirection();

            $entity
                ->setDomain($domain)
                ->setFromEmail($data['from'] ?? '')
                ->setToEmail($data['to'] ?? '')
                ->setOvhId((string)$id)
                ->setSyncedAt(new \DateTimeImmutable());

            if (isset($data['localCopy'])) {
                $entity->setLocalCopy((bool)$data['localCopy']);
            }

            $this->em->persist($entity);
            $count++;
        }

        $this->em->flush();

        // ✅ Important : retourner le nombre
        return $count;
    }


    /** Synchronise les redirections de tous les domaines gérés */
    public function syncAllDomains(): array
    {
        $domains = ['b17.fr', 'izardcom.fr'];
        $results = [];

        foreach ($domains as $domain) {
            try {
                $results[$domain] = $this->syncDomain($domain);
            } catch (\Throwable $e) {
                $this->logger->error("[OVH] Sync échouée pour $domain : {$e->getMessage()}");
                $results[$domain] = 0;
            }
        }

        return $results;
    }

    // ============================================================
    // 🔹 UTILITAIRES MÉTIER
    // ============================================================

    /** Supprime localement une redirection et côté OVH */
    public function remove(Redirection $r): bool
    {
        if ($r->getOvhId()) {
            $this->delete($r->getDomain(), $r->getOvhId());
        }

        $this->em->remove($r);
        $this->em->flush();

        $this->logger->info("[OVH] Redirection supprimée : {$r->getFromEmail()} → {$r->getToEmail()}");
        return true;
    }

}
