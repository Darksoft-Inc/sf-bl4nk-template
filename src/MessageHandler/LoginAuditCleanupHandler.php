<?php

namespace App\MessageHandler;

use App\Entity\MaintenanceRun;
use App\Maintenance\LoginAuditRetention;
use App\Message\LoginAuditCleanup;
use App\Repository\LoginAuditRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class LoginAuditCleanupHandler
{
    public function __construct(
        private readonly LoginAuditRepository $audits,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoginAuditRetention $retention,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Removes the expired rows, records the run, and returns the count so a
     * synchronous caller can report it.
     */
    public function __invoke(LoginAuditCleanup $message): int
    {
        $now = $this->clock->now();
        $removed = $this->audits->removeOlderThan($this->retention->threshold());

        $this->entityManager->persist(new MaintenanceRun(
            LoginAuditCleanup::TASK,
            $message->trigger,
            \sprintf('Removed %d login audit rows older than %d days.', $removed, $this->retention->days()),
            $now,
        ));
        $this->entityManager->flush();

        return $removed;
    }
}
