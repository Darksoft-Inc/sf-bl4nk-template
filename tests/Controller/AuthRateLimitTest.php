<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\ResetDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * The public auth routes rate-limit by client IP, and the login form
 * throttles through the firewall. Each test starts from an empty limiter
 * state: the test kernel clears the cache.rate_limiter pool on shutdown.
 */
final class AuthRateLimitTest extends WebTestCase
{
    use ResetDatabase;

    private KernelBrowser $client;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $this->resetDatabase($em);
    }

    public function testRegisterSubmissionsAreRateLimited(): void
    {
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $this->client->request('GET', '/register');
            $this->submitEmptyPasswordForm();

            // The weak submission fails validation without creating an account.
            self::assertResponseStatusCodeSame(422);
        }

        $this->client->request('GET', '/register');
        $this->submitEmptyPasswordForm();

        self::assertResponseStatusCodeSame(429);
        $this->assertRetryAfterHeader();
    }

    public function testVerifyRequestsAreRateLimited(): void
    {
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $this->client->request('GET', '/verify/email?id=999999');

            // The unknown id redirects to the registration page.
            self::assertResponseRedirects('/register');
        }

        $this->client->request('GET', '/verify/email?id=999999');

        self::assertResponseStatusCodeSame(429);
        $this->assertRetryAfterHeader();
    }

    public function testResetRequestsAreRateLimited(): void
    {
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $this->client->request('GET', '/reset-password');
            $this->client->submitForm('Send password reset email', [
                'reset_password_request_form[email]' => 'nobody@example.com',
            ]);

            // The unknown address behaves like a sent reset: the check-email page.
            self::assertResponseRedirects('/reset-password/check-email');
        }

        $this->client->request('GET', '/reset-password');
        $this->client->submitForm('Send password reset email', [
            'reset_password_request_form[email]' => 'nobody@example.com',
        ]);

        self::assertResponseStatusCodeSame(429);
        $this->assertRetryAfterHeader();
    }

    public function testLoginFormIsThrottledAfterRepeatedFailures(): void
    {
        $this->createUser('throttled@example.com', 'correct-password', true);

        // The one minute window can reset while the loop runs, so the loop
        // bounds the attempts instead of counting them exactly.
        $blocked = false;
        for ($attempt = 0; $attempt < 11; ++$attempt) {
            $this->client->request('GET', '/login');
            $this->client->submitForm('Log in', [
                '_username' => 'throttled@example.com',
                '_password' => 'wrong-password',
            ]);
            $this->client->followRedirect();

            if (str_contains((string) $this->client->getResponse()->getContent(), 'Too many failed login attempts')) {
                $blocked = true;

                break;
            }

            self::assertSelectorTextContains('.alert-danger', 'Invalid credentials.');
        }

        self::assertTrue($blocked, 'The login form never reported the throttling.');
    }

    private function submitEmptyPasswordForm(): void
    {
        // An empty password fails validation, so the attempts create no
        // account and send no email.
        $this->client->submitForm('Register', [
            'registration_form[email]' => 'limited@example.com',
            'registration_form[plainPassword]' => '',
            'registration_form[agreeTerms]' => true,
        ]);
    }

    private function assertRetryAfterHeader(): void
    {
        $retryAfter = (int) $this->client->getResponse()->headers->get('Retry-After', '0');

        self::assertGreaterThanOrEqual(1, $retryAfter);
    }

    private function createUser(string $email, string $password, bool $verified): void
    {
        $user = new User();
        $user->setEmail($email);
        $user->setIsVerified($verified);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $entityManager->persist($user);
        $entityManager->flush();
    }
}
