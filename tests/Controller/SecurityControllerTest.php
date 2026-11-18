<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SecurityControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get('doctrine.orm.entity_manager');
        $this->userRepository = $container->get(UserRepository::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

        foreach ($this->userRepository->findAll() as $user) {
            $this->entityManager->remove($user);
        }

        $this->entityManager->flush();
    }

    public function testLoginPage(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Log in');
        self::assertSelectorTextContains('h1', 'Log in');
    }

    public function testUnverifiedUserCannotLogIn(): void
    {
        $this->createUser('unverified@example.com', 'password', false);

        $this->client->request('GET', '/login');
        $this->client->submitForm('Log in', [
            '_username' => 'unverified@example.com',
            '_password' => 'password',
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Please verify your email address before logging in.');
    }

    public function testVerifiedUserCanLogInAndLogOut(): void
    {
        $this->createUser('verified@example.com', 'password', true);

        $this->client->request('GET', '/login');
        $this->client->submitForm('Log in', [
            '_username' => 'verified@example.com',
            '_password' => 'password',
        ]);

        self::assertResponseRedirects('/index');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/logout');
        self::assertResponseRedirects('/index');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/login');
        self::assertSelectorTextNotContains('body', 'You are logged in as');
    }

    private function createUser(string $email, string $password, bool $verified): void
    {
        $user = new User();
        $user->setEmail($email);
        $user->setIsVerified($verified);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
