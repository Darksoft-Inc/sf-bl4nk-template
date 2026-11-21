<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\ResetDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\PhpUnit\Attribute\UseRecord;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[UseRecord('pwnedpasswords-ranges.har')]
final class CsrfProtectionControllerTest extends WebTestCase
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

    public function testRegistrationFormRejectsInvalidCsrfToken(): void
    {
        $this->client->request('GET', '/register');
        $this->client->submitForm('Register', [
            'registration_form[email]' => 'csrf-register@example.com',
            'registration_form[plainPassword]' => 'CorrectHorseBatteryStaple42!',
            'registration_form[agreeTerms]' => true,
            'registration_form[_token]' => 'not-a-real-token',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->entityManager->getRepository(User::class)->findAll());
        self::assertEmailCount(0);
    }

    public function testResetRequestFormRejectsInvalidCsrfToken(): void
    {
        $this->client->request('GET', '/reset-password');
        $this->client->submitForm('Send password reset email', [
            'reset_password_request_form[email]' => 'csrf-reset-request@example.com',
            'reset_password_request_form[_token]' => 'not-a-real-token',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertEmailCount(0);
    }

    public function testResetPasswordFormRejectsInvalidCsrfToken(): void
    {
        $user = $this->createUser('csrf-reset-password@example.com', 'CorrectHorseBatteryStaple42!');
        $this->requestResetAndOpenResetForm('csrf-reset-password@example.com');

        $this->client->submitForm('Reset password', [
            'change_password_form[plainPassword][first]' => 'NewStrongPassword42!',
            'change_password_form[plainPassword][second]' => 'NewStrongPassword42!',
            'change_password_form[_token]' => 'not-a-real-token',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertTrue($this->passwordHasher->isPasswordValid($user, 'CorrectHorseBatteryStaple42!'));
    }

    public function testProfilePasswordFormRejectsInvalidCsrfToken(): void
    {
        $user = $this->createUser('csrf-profile@example.com', 'CorrectHorseBatteryStaple42!', true);
        $this->client->loginUser($user);

        $this->client->request('GET', '/profile');
        $this->client->submitForm('Change password', [
            'change_password_form[plainPassword][first]' => 'NewStrongPassword42!',
            'change_password_form[plainPassword][second]' => 'NewStrongPassword42!',
            'change_password_form[_token]' => 'not-a-real-token',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertTrue($this->passwordHasher->isPasswordValid($user, 'CorrectHorseBatteryStaple42!'));
    }

    public function testLoginFormRejectsInvalidCsrfToken(): void
    {
        $this->createUser('csrf-login@example.com', 'CorrectHorseBatteryStaple42!', true);

        $this->client->request('GET', '/login');
        $this->client->submitForm('Log in', [
            '_username' => 'csrf-login@example.com',
            '_password' => 'CorrectHorseBatteryStaple42!',
            '_csrf_token' => 'not-a-real-token',
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Invalid CSRF token.');
    }

    public function testLogoutRejectsInvalidCsrfToken(): void
    {
        $user = $this->createUser('csrf-logout@example.com', 'CorrectHorseBatteryStaple42!', true);
        $this->client->loginUser($user);

        // The logout token is stateless CSRF: a page visit first gives the
        // next request a same-origin Referer, so only the bad token fails.
        $this->client->request('GET', '/index');
        $this->client->request('POST', '/logout', ['_csrf_token' => 'not-a-real-token']);

        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/profile');
        self::assertResponseIsSuccessful();
    }

    private function createUser(string $email, string $password, bool $verified = false): User
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

    private function requestResetAndOpenResetForm(string $email): void
    {
        $this->client->request('GET', '/reset-password');
        $this->client->submitForm('Send password reset email', [
            'reset_password_request_form[email]' => $email,
        ]);

        self::assertResponseRedirects('/reset-password/check-email');

        $message = $this->getMailerMessage(0);
        self::assertInstanceOf(Email::class, $message);

        $messageBody = $message->getTextBody();
        self::assertIsString($messageBody);

        preg_match('#(/reset-password/reset/[a-zA-Z0-9]+)#', $messageBody, $resetLink);
        self::assertArrayHasKey(1, $resetLink);

        $this->client->request('GET', $resetLink[1]);
        self::assertResponseRedirects('/reset-password/reset');
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
    }
}
