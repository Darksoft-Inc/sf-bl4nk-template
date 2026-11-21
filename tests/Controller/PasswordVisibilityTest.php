<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\ResetDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PasswordVisibilityTest extends WebTestCase
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

    public function testRegistrationPasswordHasAToggle(): void
    {
        $crawler = $this->client->request('GET', '/register');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-action="password-visibility#toggle"]'));
    }

    public function testProfilePasswordHasTwoToggles(): void
    {
        $user = $this->createUser('toggle-profile@example.com');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/profile');

        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('[data-action="password-visibility#toggle"]'));
    }

    public function testResetPasswordHasTwoToggles(): void
    {
        $this->createUser('toggle-reset@example.com');

        $this->client->request('GET', '/reset-password');
        $this->client->submitForm('Send password reset email', [
            'reset_password_request_form[email]' => 'toggle-reset@example.com',
        ]);

        $message = $this->getMailerMessage(0);
        self::assertInstanceOf(Email::class, $message);

        $messageBody = $message->getTextBody();
        self::assertIsString($messageBody);

        preg_match('#(/reset-password/reset/[a-zA-Z0-9]+)#', $messageBody, $resetLink);
        self::assertArrayHasKey(1, $resetLink);

        $crawler = $this->client->request('GET', $resetLink[1]);
        self::assertResponseRedirects('/reset-password/reset');

        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertCount(2, $crawler->filter('[data-action="password-visibility#toggle"]'));
    }

    private function createUser(string $email): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setIsVerified(true)
        ;
        $user->setPassword($this->passwordHasher->hashPassword($user, 'CorrectHorseBatteryStaple42!'));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
