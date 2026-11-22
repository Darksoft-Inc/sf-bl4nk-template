<?php

namespace App\Maintenance;

use App\Reference\Reference;
use App\Reference\ReferenceDetail;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The retention window of the login audit. The cleanup handler that
 * removes the rows and the maintenance page that reports them both ask
 * this service, so the parameter and the threshold arithmetic live in
 * one place.
 */
#[Reference(
    slug: 'login-audit-retention',
    title: 'Login audit retention',
    summary: 'Every sign-in and every restore of a persistent session is recorded with the IP address. '
        .'The records are kept for a bounded time, then removed.',
    group: 'Account',
    details: [
        new ReferenceDetail('Recorded events', 'One row per form login and one per remember-me session restore, never one per page view.'),
        new ReferenceDetail('Retention window', 'Rows older than %app.login_audit_retention_days% days are removed.'),
        new ReferenceDetail('Removal', 'A scheduled task runs the cleanup once a day, and the maintenance page runs the same cleanup by hand.'),
    ],
)]
final class LoginAuditRetention
{
    public function __construct(
        private readonly ClockInterface $clock,
        #[Autowire(param: 'app.login_audit_retention_days')] private readonly int $retentionDays,
    ) {
    }

    public function days(): int
    {
        return $this->retentionDays;
    }

    /**
     * The time past which the cleanup removes an audit row.
     */
    public function threshold(): \DateTimeImmutable
    {
        return $this->clock->now()->modify(\sprintf('-%d days', $this->retentionDays));
    }
}
