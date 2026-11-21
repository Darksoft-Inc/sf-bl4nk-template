<?php

namespace App\Tests\Controller\Admin;

use App\Entity\User;
use App\Tests\ResetDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminUserControllerTest extends WebTestCase
{
    use ResetDatabase;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        $this->em = $em;

        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $this->resetDatabase($this->em);
    }

    public function testAnonymousVisitorsAreRedirectedToLogin(): void
    {
        $this->client->request('GET', '/admin/users');

        self::assertResponseRedirects('/login');
    }

    public function testUsersWithoutAdminRoleAreDenied(): void
    {
        $this->client->loginUser($this->createUser('plain@example.com', false));

        $this->client->request('GET', '/admin/users');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminSeesUsers(): void
    {
        $this->createUser('target@example.com', false);
        $this->client->loginUser($this->createUser('admin@example.com', true));

        $crawler = $this->client->request('GET', '/admin/users');

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Users');
        self::assertSelectorTextContains('body', 'target@example.com');

        self::assertGreaterThan(0, $crawler->filter('select[multiple]')->count());
        self::assertGreaterThan(0, $crawler->filter('option[value="ROLE_ADMIN"][selected]')->count());
    }

    public function testUserListIsPaginated(): void
    {
        $this->client->loginUser($this->createUser('admin-paged@example.com', true));

        for ($i = 1; $i <= 15; ++$i) {
            $this->createUser('member'.$i.'@example.com', false);
        }

        $crawler = $this->client->request('GET', '/admin/users');
        self::assertResponseIsSuccessful();
        self::assertCount(10, $crawler->filter('.user-row'));
        self::assertSelectorExists('.pagination');

        $crawler = $this->client->request('GET', '/admin/users?page=2');
        self::assertResponseIsSuccessful();
        self::assertCount(6, $crawler->filter('.user-row'));
    }

    public function testAdminEditsUserFromTheModalForm(): void
    {
        $target = $this->createUser('before@example.com', false);
        $this->client->loginUser($this->createUser('admin@example.com', true));

        $crawler = $this->client->request('GET', '/admin/users');
        self::assertResponseIsSuccessful();

        $token = $crawler->filter('#user_edit_'.$target->getId().'__token')->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', '/admin/users/'.$target->getId().'/edit', [
            'user_edit_'.$target->getId() => [
                'email' => 'after@example.com',
                'isVerified' => '1',
                'roles' => ['ROLE_ADMIN'],
                '_token' => $token,
            ],
        ]);

        self::assertResponseRedirects('/admin/users');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'The user has been updated.');

        $updated = $this->em->find(User::class, $target->getId());
        self::assertInstanceOf(User::class, $updated);
        self::assertSame('after@example.com', $updated->getEmail());
        self::assertTrue($updated->isVerified());
        self::assertContains('ROLE_ADMIN', $updated->getRoles());
    }

    public function testAdminCannotRemoveOwnAdminRole(): void
    {
        $admin = $this->createUser('admin@example.com', true);
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/admin/users');
        self::assertResponseIsSuccessful();

        $token = $crawler->filter('#user_edit_'.$admin->getId().'__token')->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', '/admin/users/'.$admin->getId().'/edit', [
            'user_edit_'.$admin->getId() => [
                'email' => 'admin@example.com',
                'isVerified' => '1',
                'roles' => [],
                '_token' => $token,
            ],
        ]);

        self::assertResponseRedirects('/admin/users');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'You cannot remove your own admin role.');

        $updated = $this->em->find(User::class, $admin->getId());
        self::assertInstanceOf(User::class, $updated);
        self::assertContains('ROLE_ADMIN', $updated->getRoles());
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
