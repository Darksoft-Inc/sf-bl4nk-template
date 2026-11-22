<?php

namespace App\Tests\Controller;

use App\Entity\LoginAudit;
use App\Entity\LoginAuditType;
use App\Entity\User;
use App\Tests\ResetDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Checks what the login audit records: a form login writes one row, requests
 * from a live session write nothing, and the restore of a persistent session
 * writes a "resume" row.
 */
final class LoginAuditTest extends WebTestCase
{
    use ResetDatabase;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get('doctrine.orm.entity_manager');
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $this->resetDatabase($this->entityManager);
    }

    public function testFormLoginRecordsOneRowAndTheUserMetadata(): void
    {
        $user = $this->createUser('connected@example.com');

        $this->client->request('GET', '/login');
        $this->client->submitForm('Log in', [
            '_username' => 'connected@example.com',
            '_password' => 'password',
        ]);
        self::assertResponseRedirects('/');

        // The second and third requests come from the same live session and
        // must not add audit rows or rewrite the metadata.
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/profile');
        self::assertResponseIsSuccessful();

        $rows = $this->loginAuditRows();
        self::assertCount(1, $rows, 'Only the form login writes a row, not every request.');

        $row = $rows[0];
        self::assertSame(LoginAuditType::Login, $row->getType());
        self::assertSame('connected@example.com', $row->getIdentifier());
        self::assertSame('127.0.0.1', $row->getIp());
        self::assertSame($user->getId(), $row->getUser()?->getId());
        self::assertNotNull($row->getUserAgent());

        // The test client reboots the kernel on each request, so the user is
        // re-read from the database instead of refreshing the stale instance.
        $stored = $this->storedUser('connected@example.com');
        self::assertNotNull($stored->getLastConnectedAt());
        self::assertSame('127.0.0.1', $stored->getLastIp());

        self::assertSelectorTextContains('.user-last-connection', '127.0.0.1');
    }

    public function testPersistentSessionResumeRecordsAResumeRow(): void
    {
        $user = $this->createUser('returning@example.com');

        $this->client->request('GET', '/login');
        $this->client->submitForm('Log in', [
            '_username' => 'returning@example.com',
            '_password' => 'password',
            '_remember_me' => true,
        ]);
        self::assertResponseRedirects('/');

        $rememberMe = $this->findRememberMeCookie();
        self::assertNotNull($rememberMe, 'The login response must set a remember-me cookie.');

        // A returning visitor: the session is gone, only the persistent
        // cookie is left, so the firewall re-authenticates from it.
        $this->client->getCookieJar()->clear();
        $this->client->getCookieJar()->set($rememberMe);

        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $rows = $this->loginAuditRows();
        self::assertCount(2, $rows, 'The form login and the session restore each write one row.');

        $resume = $rows[0];
        self::assertSame(LoginAuditType::Resume, $resume->getType());
        self::assertSame('returning@example.com', $resume->getIdentifier());
        self::assertSame('127.0.0.1', $resume->getIp());

        $stored = $this->storedUser('returning@example.com');
        self::assertSame('127.0.0.1', $stored->getLastIp());
    }

    public function testLoginWithoutTheCheckboxSetsNoRememberMeCookie(): void
    {
        $this->createUser('plain@example.com');

        $this->client->request('GET', '/login');
        $this->client->submitForm('Log in', [
            '_username' => 'plain@example.com',
            '_password' => 'password',
        ]);
        self::assertResponseRedirects('/');

        self::assertNull($this->findRememberMeCookie());
    }

    /**
     * @return list<LoginAudit>
     */
    private function loginAuditRows(): array
    {
        return $this->entityManager->getRepository(LoginAudit::class)
            ->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    private function storedUser(string $email): User
    {
        $stored = $this->entityManager->createQuery('SELECT u FROM App\Entity\User u WHERE u.email = :email')
            ->setParameter('email', $email)
            ->getSingleResult();
        \assert($stored instanceof User);

        return $stored;
    }

    private function findRememberMeCookie(): ?Cookie
    {
        foreach ($this->client->getCookieJar()->all() as $cookie) {
            if ('REMEMBERME' === $cookie->getName()) {
                return $cookie;
            }
        }

        return null;
    }

    private function createUser(string $email): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setIsVerified(true)
        ;
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
