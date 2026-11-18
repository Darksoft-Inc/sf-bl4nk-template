<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ProfileControllerTest extends WebTestCase
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

    public function testProfileRequiresLogin(): void
    {
        $this->client->request('GET', '/profile');

        self::assertResponseRedirects('/login');
    }

    public function testProfileShowsDetailsAndChangesPassword(): void
    {
        $user = $this->createUser('profile@example.com', 'CorrectHorseBatteryStaple42!', true);
        $this->client->loginUser($user);

        $this->client->request('GET', '/profile');

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Profile');
        self::assertSelectorTextContains('h1', 'Profile');
        self::assertSelectorTextContains('body', 'profile@example.com');
        self::assertSelectorTextContains('body', 'Verified');

        $this->client->submitForm('Change password', [
            'change_password_form[plainPassword][first]' => 'AnotherStrongPassword42!',
            'change_password_form[plainPassword][second]' => 'AnotherStrongPassword42!',
        ]);

        self::assertResponseRedirects('/profile');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Your password has been changed.');

        $updatedUser = $this->userRepository->findOneBy(['email' => 'profile@example.com']);
        self::assertInstanceOf(User::class, $updatedUser);
        self::assertTrue($this->passwordHasher->isPasswordValid($updatedUser, 'AnotherStrongPassword42!'));
    }

    private function createUser(string $email, string $password, bool $verified): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setIsVerified($verified)
        ;
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
