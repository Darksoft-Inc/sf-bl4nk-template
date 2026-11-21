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

        $formName = 'user_edit_'.$target->getId();

        $this->client->submitForm('Save', [
            $formName.'[email]' => 'after@example.com',
            $formName.'[isVerified]' => true,
            $formName.'[isAdmin]' => true,
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
