<?php

namespace App\Tests\Integration;

use App\Entity\LoginAudit;
use App\Entity\LoginAuditType;
use App\Entity\MaintenanceRun;
use App\Entity\User;
use App\Task\LoginAuditCleanupTask;
use App\Tests\ResetDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Runs the scheduled task service the way the scheduler worker would call
 * it. The message dispatch is synchronous, so one call covers the whole
 * chain: the task dispatches, the handler removes the rows and records the
 * run with the "schedule" trigger.
 */
final class LoginAuditCleanupTaskTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use ResetDatabase;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        $this->em = $em;

        $this->resetDatabase($this->em);
    }

    public function testTheScheduledTaskRemovesExpiredRowsAndRecordsTheRun(): void
    {
        // 2026-11-21: rows seeded before this moment are older than the
        // retention period at the mocked time.
        self::mockTime('2026-11-21 12:00:00');

        $user = $this->createUser('scheduled@example.com');
        $this->seedRow($user, '10.0.0.1', new \DateTimeImmutable('2026-08-01 10:00:00'));
        $this->seedRow($user, '10.0.0.2', new \DateTimeImmutable('2026-11-20 10:00:00'));

        /** @var LoginAuditCleanupTask $task */
        $task = static::getContainer()->get(LoginAuditCleanupTask::class);
        $task();

        $rows = $this->em->getRepository(LoginAudit::class)
            ->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']);
        self::assertCount(1, $rows, 'Only the row past the retention period is removed.');
        self::assertSame('10.0.0.2', $rows[0]->getIp());

        $runs = $this->em->getRepository(MaintenanceRun::class)->findAll();
        self::assertCount(1, $runs);
        self::assertSame('login_audit_cleanup', $runs[0]->getTask());
        self::assertSame('schedule', $runs[0]->getTrigger());
    }

    private function createUser(string $email): User
    {
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = (new User())
            ->setEmail($email)
            ->setIsVerified(true)
        ;
        $user->setPassword($hasher->hashPassword($user, 'password'));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function seedRow(User $user, string $ip, \DateTimeImmutable $createdAt): void
    {
        $this->em->persist(new LoginAudit($user, LoginAuditType::Login, $createdAt, $ip, 'Symfony BrowserKit'));
        $this->em->flush();
    }
}
