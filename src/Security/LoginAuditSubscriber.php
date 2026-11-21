<?php

namespace App\Security;

use App\Entity\LoginAudit;
use App\Entity\LoginAuditType;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Authenticator\RememberMeAuthenticator;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Records one audit row per authentication and keeps the user metadata
 * current. The security system dispatches LoginSuccessEvent when an
 * authenticator runs: the form login, or the remember-me authenticator when
 * a visitor returns and the session is gone. Requests served from a live
 * session restore the token without any event, so the audit table only gains
 * a row on a new login or a persistent-session restore.
 */
final class LoginAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $ip = $event->getRequest()->getClientIp();
        $now = $this->clock->now();
        $type = $event->getAuthenticator() instanceof RememberMeAuthenticator
            ? LoginAuditType::Resume
            : LoginAuditType::Login;

        $this->entityManager->persist(new LoginAudit(
            user: $user,
            type: $type,
            createdAt: $now,
            ip: $ip,
            userAgent: $event->getRequest()->headers->get('User-Agent'),
        ));

        $user->setLastConnectedAt($now);
        $user->setLastIp($ip);

        $this->entityManager->flush();
    }
}
