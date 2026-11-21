<?php

namespace App\Task;

use App\Message\LoginAuditCleanup;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

/**
 * The schedule entry for the login audit cleanup. A worker running
 * "messenger:consume scheduler_default" calls this service once a day and it
 * dispatches the message; the messenger routing then decides where the
 * message runs, inline in this app because only the sync transport exists.
 */
#[AsPeriodicTask(frequency: '1 day')]
final class LoginAuditCleanupTask
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(): void
    {
        $this->bus->dispatch(new LoginAuditCleanup(LoginAuditCleanup::TRIGGER_SCHEDULE));
    }
}
