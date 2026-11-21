<?php

namespace App\Message;

/**
 * Asks for the removal of the login audit rows past the retention period.
 * The scheduled task dispatches it with TRIGGER_SCHEDULE, the maintenance
 * page with TRIGGER_MANUAL.
 */
final class LoginAuditCleanup
{
    public const string TASK = 'login_audit_cleanup';
    public const string TRIGGER_SCHEDULE = 'schedule';
    public const string TRIGGER_MANUAL = 'manual';

    public function __construct(
        public readonly string $trigger = self::TRIGGER_MANUAL,
    ) {
    }
}
