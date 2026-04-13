<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * À chaque connexion réussie, la prochaine visite sur /compte relancera une synchro des redirections OVH.
 */
final class PortalAccountSyncStateSubscriber implements EventSubscriberInterface
{
    public const SESSION_PORTAL_REDIRECTIONS_SYNCED = 'portal_redirections_synced';

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $event->getRequest()->getSession()->remove(self::SESSION_PORTAL_REDIRECTIONS_SYNCED);
    }
}
