<?php

namespace App\Maintenance;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The retention window of the login audit. The cleanup handler that
 * removes the rows and the maintenance page that reports them both ask
 * this service, so the parameter and the threshold arithmetic live in
 * one place.
 */
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
