<?php

namespace App\Tests\Controller\Admin;

use App\Entity\LoginAudit;
use App\Entity\LoginAuditType;
use App\Entity\User;
use App\Tests\ResetDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminLoginAuditControllerTest extends WebTestCase
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
        $this->client->request('GET', '/admin/login-audit');

        self::assertResponseRedirects('/login');
    }

    public function testUsersWithoutAdminRoleAreDenied(): void
    {
        $this->client->loginUser($this->createUser('member@example.com', false));

        $this->client->request('GET', '/admin/login-audit');

        self::assertResponseStatusCodeSame(403);
    }

    public function testListShowsRowsNewestFirst(): void
    {
        $user = $this->createUser('listed@example.com', false);
        $this->seedRow($user, '10.0.0.1', new \DateTimeImmutable('2026-11-01 10:00:00'));
        $this->seedRow($user, '192.168.1.5', new \DateTimeImmutable('2026-11-10 10:00:00'));

        $this->client->loginUser($this->createUser('admin@example.com', true));

        $crawler = $this->client->request('GET', '/admin/login-audit');

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Login audit');

        $rows = $crawler->filter('.audit-row');
        self::assertCount(2, $rows);
        self::assertStringContainsString('192.168.1.5', $rows->eq(0)->text());
        self::assertStringContainsString('10.0.0.1', $rows->eq(1)->text());
        self::assertStringContainsString('listed@example.com', $rows->eq(0)->text());
    }

    public function testIpSearchMatchesAsSubstringOrAsTypedPattern(): void
    {
        $user = $this->createUser('search@example.com', false);
        $this->seedRow($user, '10.0.0.1', new \DateTimeImmutable('2026-11-01 10:00:00'));
        $this->seedRow($user, '192.168.1.5', new \DateTimeImmutable('2026-11-10 10:00:00'));

        $this->client->loginUser($this->createUser('admin@example.com', true));

        // Without wildcards the pattern matches any IP containing it.
        $crawler = $this->client->request('GET', '/admin/login-audit', [
            'login_audit_search' => ['ip' => '168.1'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.audit-row'));
        self::assertStringContainsString('192.168.1.5', $crawler->filter('.audit-row')->eq(0)->text());

        // With a wildcard the pattern is used as typed.
        $crawler = $this->client->request('GET', '/admin/login-audit', [
            'login_audit_search' => ['ip' => '10.0.0.%'],
        ]);

        self::assertCount(1, $crawler->filter('.audit-row'));
        self::assertStringContainsString('10.0.0.1', $crawler->filter('.audit-row')->eq(0)->text());
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
