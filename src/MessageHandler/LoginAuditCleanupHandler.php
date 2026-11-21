<?php

namespace App\MessageHandler;

use App\Entity\MaintenanceRun;
use App\Message\LoginAuditCleanup;
use App\Repository\LoginAuditRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class LoginAuditCleanupHandler
{
    public function __construct(
        private readonly LoginAuditRepository $audits,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
        #[Autowire(param: 'app.login_audit_retention_days')] private readonly int $retentionDays,
    ) {
    }

    /**
     * Removes the expired rows, records the run, and returns the count so a
     * synchronous caller can report it.
     */
    public function __invoke(LoginAuditCleanup $message): int
    {
        $now = $this->clock->now();
        $removed = $this->audits->removeOlderThan($now->modify(\sprintf('-%d days', $this->retentionDays)));

        $this->entityManager->persist(new MaintenanceRun(
            LoginAuditCleanup::TASK,
            $message->trigger,
            \sprintf('Removed %d login audit rows older than %d days.', $removed, $this->retentionDays),
            $now,
        ));
        $this->entityManager->flush();

        return $removed;
    }
}
