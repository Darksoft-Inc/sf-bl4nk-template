<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\ResetDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\PhpUnit\Attribute\UseRecord;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[UseRecord('pwnedpasswords-ranges.har')]
final class ResetPasswordControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use ResetDatabase;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        $this->em = $em;

        $this->userRepository = $container->get(UserRepository::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $this->resetDatabase($this->em);
    }

    public function testResetPasswordController(): void
    {
        // Create a test user
        $user = (new User())
            ->setEmail('me@example.com')
            ->setPassword('a-test-password-that-will-be-changed-later')
        ;
        $this->em->persist($user);
        $this->em->flush();

        // Test Request reset password page
        $this->client->request('GET', '/reset-password');

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Reset your password');

        // Submit the reset password form and test email message is queued / sent
        $this->client->submitForm('Send password reset email', [
            'reset_password_request_form[email]' => 'me@example.com',
        ]);

        // Ensure the reset password email was sent
        // Use either assertQueuedEmailCount() || assertEmailCount() depending on your mailer setup
        // self::assertQueuedEmailCount(1);
        self::assertEmailCount(1);

        self::assertCount(1, $messages = $this->getMailerMessages());

        self::assertEmailAddressContains($messages[0], 'from', 'admin@example.com');
        self::assertEmailAddressContains($messages[0], 'to', 'me@example.com');
        self::assertEmailSubjectContains($messages[0], 'Your password reset request');
        self::assertEmailTextBodyContains($messages[0], 'This link will expire in 1 hour.');

        self::assertResponseRedirects('/reset-password/check-email');

        // Test check email landing page shows correct "expires at" time
        $crawler = $this->client->followRedirect();

        self::assertPageTitleContains('Password Reset Email Sent');
        self::assertStringContainsString('This link will expire in 1 hour', $crawler->html());

        // Test the link sent in the email is valid
        /** @var array<TemplatedEmail> $messages */
        $email = $messages[0]->getTextBody();
        self::assertIsString($email);
        preg_match('#(/reset-password/reset/[a-zA-Z0-9]+)#', $email, $resetLink);
        self::assertArrayHasKey(1, $resetLink);

        $this->client->request('GET', $resetLink[1]);

        self::assertResponseRedirects('/reset-password/reset');

        $this->client->followRedirect();

        // Test we can set a new password
        $this->client->submitForm('Reset password', [
            'change_password_form[plainPassword][first]' => 'newStrongPassword',
            'change_password_form[plainPassword][second]' => 'newStrongPassword',
        ]);

        self::assertResponseRedirects('/index');

        $user = $this->userRepository->findOneBy(['email' => 'me@example.com']);

        self::assertInstanceOf(User::class, $user);

        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($passwordHasher->isPasswordValid($user, 'newStrongPassword'));
    }

    public function testResetRequestDoesNotRevealWhetherTheAccountExists(): void
    {
        $this->client->request('GET', '/reset-password');
        $this->client->submitForm('Send password reset email', [
            'reset_password_request_form[email]' => 'unknown@example.com',
        ]);

        self::assertResponseRedirects('/reset-password/check-email');
        self::assertEmailCount(0);
        $unknownPage = $this->client->followRedirect()->filter('body')->text();

        $this->createUser('enumeration@example.com', 'CorrectHorseBatteryStaple42!');

        $this->client->request('GET', '/reset-password');
        $this->client->submitForm('Send password reset email', [
            'reset_password_request_form[email]' => 'enumeration@example.com',
        ]);

        self::assertResponseRedirects('/reset-password/check-email');
        self::assertEmailCount(1);
        $knownPage = $this->client->followRedirect()->filter('body')->text();

        self::assertSame($unknownPage, $knownPage);
    }

    public function testResetWithInvalidTokenShowsErrorOnRequestPage(): void
    {
        $this->client->request('GET', '/reset-password/reset/invalid-reset-token');
        self::assertResponseRedirects('/reset-password/reset');
        $this->client->followRedirect();

        self::assertResponseRedirects('/reset-password');
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'There was a problem validating your password reset request - The reset password link is invalid. Please try to reset your password again.');
        self::assertEmailCount(0);
    }

    public function testResetRejectsMismatchedPasswords(): void
    {
        $user = $this->createUser('mismatch@example.com', 'CorrectHorseBatteryStaple42!');
        $this->openResetFormFor('mismatch@example.com');

        $this->client->submitForm('Reset password', [
            'change_password_form[plainPassword][first]' => 'NewStrongPassword42!',
            'change_password_form[plainPassword][second]' => 'DifferentPassword42!',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'The password fields must match.');
        self::assertTrue($this->passwordHasher->isPasswordValid($user, 'CorrectHorseBatteryStaple42!'));
    }

    public function testResetRejectsWeakPassword(): void
    {
        $user = $this->createUser('weak-reset@example.com', 'CorrectHorseBatteryStaple42!');
        $this->openResetFormFor('weak-reset@example.com');

        $this->client->submitForm('Reset password', [
            'change_password_form[plainPassword][first]' => 'password',
            'change_password_form[plainPassword][second]' => 'password',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'Your password should be at least 12 characters');
        self::assertTrue($this->passwordHasher->isPasswordValid($user, 'CorrectHorseBatteryStaple42!'));
    }

    public function testConsumedResetTokenCannotBeUsedAgain(): void
    {
        $user = $this->createUser('reuse@example.com', 'CorrectHorseBatteryStaple42!');
        $resetLink = $this->openResetFormFor('reuse@example.com');

        $this->client->submitForm('Reset password', [
            'change_password_form[plainPassword][first]' => 'NewStrongPassword42!',
            'change_password_form[plainPassword][second]' => 'NewStrongPassword42!',
        ]);

        self::assertResponseRedirects('/index');

        $this->client->request('GET', $resetLink);
        self::assertResponseRedirects('/reset-password/reset');
        $this->client->followRedirect();

        self::assertResponseRedirects('/reset-password');
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'There was a problem validating your password reset request - The reset password link is invalid. Please try to reset your password again.');

        $user = $this->userRepository->findOneBy(['email' => 'reuse@example.com']);
        self::assertInstanceOf(User::class, $user);
        self::assertTrue($this->passwordHasher->isPasswordValid($user, 'NewStrongPassword42!'));
    }

    public function testRejectedResetTokenIsRemovedFromSession(): void
    {
        $user = $this->createUser('reuse-session@example.com', 'CorrectHorseBatteryStaple42!');
        $resetLink = $this->openResetFormFor('reuse-session@example.com');

        $this->client->submitForm('Reset password', [
            'change_password_form[plainPassword][first]' => 'NewStrongPassword42!',
            'change_password_form[plainPassword][second]' => 'NewStrongPassword42!',
        ]);

        self::assertResponseRedirects('/index');

        $this->client->request('GET', $resetLink);
        self::assertResponseRedirects('/reset-password/reset');
        $this->client->followRedirect();

        self::assertResponseRedirects('/reset-password');
        $this->client->followRedirect();

        // The rejected token must not stay in the session: without a token in
        // the URL or the session, the route reports a missing token instead of
        // replaying the validation error.
        $this->client->request('GET', '/reset-password/reset');
        self::assertResponseStatusCodeSame(404);

        $user = $this->userRepository->findOneBy(['email' => 'reuse-session@example.com']);
        self::assertInstanceOf(User::class, $user);
        self::assertTrue($this->passwordHasher->isPasswordValid($user, 'NewStrongPassword42!'), 'actual hash: '.$user->getPassword());
    }

    public function testRejectedResetTokenRemovesEverySessionEntry(): void
    {
        $this->createUser('session-cleanup@example.com', 'CorrectHorseBatteryStaple42!');
        $this->openResetFormFor('session-cleanup@example.com');

        $this->client->request('GET', '/reset-password/reset/invalid-reset-token');
        self::assertResponseRedirects('/reset-password/reset');
        $this->client->followRedirect();

        self::assertResponseRedirects('/reset-password');
        $this->client->followRedirect();

        // A rejected link must clear every reset entry the bundle keeps in
        // the session, not only the public token: the token object stored by
        // the request step would otherwise outlive its own link, and the
        // check-email page would keep rendering the lifetime of a dead
        // token instead of the generic one.
        $session = $this->clientSession();
        self::assertFalse($session->has('ResetPasswordPublicToken'));
        self::assertFalse($session->has('ResetPasswordToken'));
    }

    public function testExpiredResetTokenIsRejected(): void
    {
        $user = $this->createUser('expired-reset@example.com', 'CorrectHorseBatteryStaple42!');
        $resetLink = $this->openResetFormFor('expired-reset@example.com');

        // The bundle builds the expiry date and the entity checks it against
        // the global clock, so a mocked time moves the token past its 1 hour
        // lifetime.
        self::mockTime('+2 hours');

        $this->client->request('GET', $resetLink);
        self::assertResponseRedirects('/reset-password/reset');
        $this->client->followRedirect();

        self::assertResponseRedirects('/reset-password');
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'There was a problem validating your password reset request - The link in your email is expired. Please try to reset your password again.');
        self::assertTrue($this->passwordHasher->isPasswordValid($user, 'CorrectHorseBatteryStaple42!'));
    }

    public function testResetRejectsCompromisedPassword(): void
    {
        $user = $this->createUser('compromised@example.com', 'CorrectHorseBatteryStaple42!');
        $this->openResetFormFor('compromised@example.com');

        // "Password123456!" appears thousands of times in breach corpora. The
        // constraint queries api.pwnedpasswords.com for the SHA-1 range; the
        // exchange is recorded once and replayed from tests/recordings/.
        $this->client->submitForm('Reset password', [
            'change_password_form[plainPassword][first]' => 'Password123456!',
            'change_password_form[plainPassword][second]' => 'Password123456!',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'This password has been leaked in a data breach, it must not be used. Please use another password.');
        self::assertTrue($this->passwordHasher->isPasswordValid($user, 'CorrectHorseBatteryStaple42!'));
    }

    private function createUser(string $email, string $password): User
    {
        $user = (new User())->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function openResetFormFor(string $email): string
    {
        $this->client->request('GET', '/reset-password');
        $this->client->submitForm('Send password reset email', [
            'reset_password_request_form[email]' => $email,
        ]);

        self::assertResponseRedirects('/reset-password/check-email');

        $resetLink = $this->extractResetLink();

        $this->client->request('GET', $resetLink);
        self::assertResponseRedirects('/reset-password/reset');
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();

        return $resetLink;
    }

    private function extractResetLink(): string
    {
        $message = $this->getMailerMessage(0);
        self::assertInstanceOf(Email::class, $message);

        $messageBody = $message->getTextBody();
        self::assertIsString($messageBody);

        preg_match('#(/reset-password/reset/[a-zA-Z0-9]+)#', $messageBody, $resetLink);
        self::assertArrayHasKey(1, $resetLink);

        return $resetLink[1];
    }

    /**
     * The persisted session of the test client. The test environment
     * stores sessions through the mock file storage, whose files survive
     * the request that wrote them, so the session can be read back after
     * a response and its entries asserted.
     */
    private function clientSession(): SessionInterface
    {
        $cookie = $this->client->getCookieJar()->get('MOCKSESSID');
        self::assertNotNull($cookie, 'The client carries no session cookie.');

        $factory = static::getContainer()->get('session.storage.factory');

        $session = new Session($factory->createStorage(null));
        $session->setId($cookie->getValue());
        $session->start();

        return $session;
    }
}
