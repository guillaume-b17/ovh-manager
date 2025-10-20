<?php

namespace App\Service;

use App\Entity\EmailAccount;
use App\Entity\Responder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class OvhResponderService
{
    public function __construct(
        private readonly OvhClientService $ovh,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * 🔹 Synchronise un répondeur depuis OVH
     */
    public function syncResponder(EmailAccount $account): ?Responder
    {
        $domain = $account->getDomain();
        $name   = $account->getAccountName();

        try {
            $data = $this->ovh->getClient()->get("/email/domain/$domain/responder/$name");
        } catch (\Throwable $e) {
            $this->logger->info("Aucun répondeur pour $name@$domain : " . $e->getMessage());
            return null;
        }

        $tzParis = new \DateTimeZone('Europe/Paris');
        $responder = $this->em->getRepository(Responder::class)
            ->findOneBy(['emailAccount' => $account]) ?? new Responder();

        $responder->setEmailAccount($account);
        $responder->setContent($data['content'] ?? null);
        $responder->setCopy($data['copy'] ?? false);
        $responder->setCopyTo($data['copyTo'] ?? null);

        // 🔁 OVH renvoie en UTC → conversion en Europe/Paris
        $responder->setFromDate(isset($data['from']) ? (new \DateTimeImmutable($data['from']))->setTimezone($tzParis) : null);
        $responder->setToDate(isset($data['to']) ? (new \DateTimeImmutable($data['to']))->setTimezone($tzParis) : null);

        $responder->setDateSync(new \DateTimeImmutable());

        $this->em->persist($responder);
        $this->em->flush();

        return $responder;
    }

    /**
     * 🔹 Synchronise tous les répondeurs liés aux comptes email
     */
    public function syncAllResponders(): array
    {
        $accounts = $this->em->getRepository(EmailAccount::class)->findAll();
        $synced = 0;
        $errors = [];

        foreach ($accounts as $account) {
            try {
                $responder = $this->syncResponder($account);
                if ($responder !== null) {
                    $synced++;
                }
            } catch (\Throwable $e) {
                $errors[] = $account->getEmail() . ' : ' . $e->getMessage();
                $this->logger->error("[Responder Sync] " . $e->getMessage());
            }
        }

        return ['synced' => $synced, 'errors' => $errors];
    }

    /**
     * 🔹 Crée un répondeur sur OVH
     */
    public function createResponderOnOvh(Responder $responder): bool
    {
        $account = $responder->getEmailAccount();
        if (!$account) {
            throw new \RuntimeException('Ce répondeur n’a pas de compte email associé.');
        }

        $domain = $account->getDomain();
        $name   = $account->getAccountName();
        $tzParis = new \DateTimeZone('Europe/Paris');
        $now = new \DateTimeImmutable('now', $tzParis);

        // 🔸 Validation des dates
        $from = $responder->getFromDate();
        $to   = $responder->getToDate();

        if ($from && $from < $now) {
            $this->logger->warning("[Responder Validation] Date 'from' antérieure : correction automatique");
            $from = $now;
        }

        if ($to && $to < $from) {
            $this->logger->warning("[Responder Validation] Date 'to' avant 'from' : correction automatique");
            $to = $from->modify('+1 day');
        }

        $data = [
            'account' => $name,
            'content' => $responder->getContent(),
            'copy'    => (bool)$responder->getCopy(),
            'copyTo'  => $responder->getCopyTo() ?: '',
            'from'    => $from?->format('Y-m-d\TH:i:s'),
            'to'      => $to?->format('Y-m-d\TH:i:s'),
        ];

        try {
            $this->ovh->getClient()->post("/email/domain/$domain/responder", $data);
            $this->logger->info("[Responder Create] ✅ Créé sur OVH : {$name}@{$domain}");
            return true;
        } catch (\Throwable $e) {
            $this->logger->error("[Responder Create] ❌ Erreur pour {$name}@{$domain} : " . $e->getMessage());
            return false;
        }
    }

    /**
     * 🔹 Met à jour un répondeur sur OVH
     */
    public function updateResponderOnOvh(Responder $responder): bool
    {
        $account = $responder->getEmailAccount();
        if (!$account) {
            throw new \RuntimeException('Ce répondeur n’a pas de compte email associé.');
        }

        $domain = $account->getDomain();
        $name   = $account->getAccountName();
        $tzParis = new \DateTimeZone('Europe/Paris');
        $now = new \DateTimeImmutable('now', $tzParis);

        $from = $responder->getFromDate();
        $to   = $responder->getToDate();

        if ($from && $from < $now) {
            $this->logger->warning("[Responder Validation] Date 'from' antérieure : correction automatique");
            $from = $now;
        }

        if ($to && $to < $from) {
            $this->logger->warning("[Responder Validation] Date 'to' avant 'from' : correction automatique");
            $to = $from->modify('+1 day');
        }

        $data = [
            'content' => $responder->getContent(),
            'from'    => $from?->format('Y-m-d\TH:i:s'),
            'to'      => $to?->format('Y-m-d\TH:i:s'),
        ];

        try {
            $this->ovh->getClient()->put("/email/domain/$domain/responder/$name", $data);
            $this->logger->info("[Responder Update] ✅ Mis à jour sur OVH : {$name}@{$domain}");
            return true;
        } catch (\Throwable $e) {
            $this->logger->error("[Responder Update] ❌ Erreur sur {$name}@{$domain} : " . $e->getMessage());
            return false;
        }
    }

    /**
     * 🔹 Supprime un répondeur sur OVH
     */
    public function deleteResponderOnOvh(Responder $responder): bool
    {
        $account = $responder->getEmailAccount();
        if (!$account) {
            throw new \RuntimeException('Ce répondeur n’a pas de compte email associé.');
        }

        $domain = $account->getDomain();
        $name   = $account->getAccountName();

        try {
            $this->ovh->getClient()->delete("/email/domain/$domain/responder/$name");
            $this->logger->info("[Responder Delete] 🗑 Supprimé sur OVH : {$name}@{$domain}");
            return true;
        } catch (\Throwable $e) {
            $this->logger->error("[Responder Delete] ❌ Erreur sur {$name}@{$domain} : " . $e->getMessage());
            return false;
        }
    }
}
