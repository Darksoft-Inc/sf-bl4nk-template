<?php

namespace App\Tests\Controller\Admin;

use App\Entity\LoginAudit;
use App\Entity\LoginAuditType;
use App\Entity\MaintenanceRun;
use App\Entity\User;
use App\Tests\ResetDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminMaintenanceCleanupControllerTest extends WebTestCase
{
    use ResetDatabase;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $this->resetDatabase($this->em);
    }

    public function testAnonymousVisitorsAreRedirectedToLogin(): void
    {
        $this->client->request('GET', '/admin/maintenance/cleanup');

        self::assertResponseRedirects('/login');
    }

    public function testUsersWithoutAdminRoleAreDenied(): void
    {
        $this->client->loginUser($this->createUser('member@example.com', false));

        $this->client->request('GET', '/admin/maintenance/cleanup');

        self::assertResponseStatusCodeSame(403);
    }

    public function testPageCountsTheExpiredRows(): void
    {
        $user = $this->createUser('counted@example.com', false);
        $this->seedRow($user, '10.0.0.1', new \DateTimeImmutable('2026-08-01 10:00:00'));
        $this->seedRow($user, '10.0.0.2', new \DateTimeImmutable());

        $this->client->loginUser($this->createUser('admin@example.com', true));

        $crawler = $this->client->request('GET', '/admin/maintenance/cleanup');

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Login audit cleanup');

        $help = $crawler->filter('.cleanup-help')->text();
        self::assertStringContainsString('One login audit row is older than the 90 day retention period', $help);

        self::assertCount(0, $crawler->filter('.run-row'));
        self::assertSelectorTextContains('.run-none', 'The cleanup task has not run yet.');
    }

    public function testManualRunRemovesExpiredRowsAndRecordsTheRun(): void
    {
        $user = $this->createUser('cleaned@example.com', false);
        $this->seedRow($user, '10.0.0.1', new \DateTimeImmutable('2026-08-01 10:00:00'));
        $this->seedRow($user, '10.0.0.2', new \DateTimeImmutable());

        $this->client->loginUser($this->createUser('admin@example.com', true));

        $crawler = $this->client->request('GET', '/admin/maintenance/cleanup');
        self::assertResponseIsSuccessful();

        $token = $crawler->filter('input[name="_csrf_token"]')->first()->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', '/admin/maintenance/cleanup/run', [
            '_csrf_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/maintenance/cleanup');
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'Removed one login audit row.');

        $remaining = $this->loginAuditRows();
        self::assertCount(1, $remaining, 'Only the row past the retention period is removed.');
        self::assertSame('10.0.0.2', $remaining[0]->getIp());

        $runs = $this->maintenanceRuns();
        self::assertCount(1, $runs);
        self::assertSame('login_audit_cleanup', $runs[0]->getTask());
        self::assertSame('manual', $runs[0]->getTrigger());
        self::assertStringContainsString('Removed 1 login audit rows', $runs[0]->getResult());
    }

    public function testManualRunWithABadTokenChangesNothing(): void
    {
        $user = $this->createUser('kept@example.com', false);
        $this->seedRow($user, '10.0.0.1', new \DateTimeImmutable('2026-08-01 10:00:00'));

        $this->client->loginUser($this->createUser('admin@example.com', true));

        $this->client->request('POST', '/admin/maintenance/cleanup/run', [
            '_csrf_token' => 'not-the-token',
        ]);

        self::assertResponseRedirects('/admin/maintenance/cleanup');
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'The cleanup task did not run.');
        self::assertCount(1, $this->loginAuditRows());
        self::assertCount(0, $this->maintenanceRuns());
    }

    /**
     * @return list<LoginAudit>
     */
    private function loginAuditRows(): array
    {
        return $this->em->getRepository(LoginAudit::class)
            ->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    /**
     * @return list<MaintenanceRun>
     */
    private function maintenanceRuns(): array
    {
        return $this->em->getRepository(MaintenanceRun::class)->findAll();
    }

    private function seedRow(User $user, string $ip, \DateTimeImmutable $createdAt): void
    {
        $this->em->persist(new LoginAudit($user, LoginAuditType::Login, $createdAt, $ip, 'Symfony BrowserKit'));
        $this->em->flush();
    }

    private function createUser(string $email, bool $admin): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setIsVerified(true)
            ->setRoles($admin ? ['ROLE_ADMIN'] : [])
        ;
        $user->setPassword($this->passwordHasher->hashPassword($user, 'CorrectHorseBatteryStaple42!'));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
