<?php

namespace App\Tests\Controller;

use App\Repository\UserRepository;
use App\Tests\ResetDatabase;
use Doctrine\ORM\EntityManager;
use Symfony\Bridge\PhpUnit\Attribute\UseRecord;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[UseRecord('pwnedpasswords-ranges.har')]
final class RegistrationControllerTest extends WebTestCase
{
    use ResetDatabase;

    private KernelBrowser $client;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $container = static::getContainer();

        /** @var EntityManager $em */
        $em = $container->get('doctrine')->getManager();
        $this->userRepository = $container->get(UserRepository::class);

        $this->resetDatabase($em);
    }

    public function testRegister(): void
    {
        // Register a new user
        $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Register');

        // The terms checkbox label links to the placeholder terms page.
        self::assertSelectorExists('label.form-check-label a[href="/docs/terms"]');

        $this->client->submitForm('Register', [
            'registration_form[email]' => 'me@example.com',
            'registration_form[plainPassword]' => 'CorrectHorseBatteryStaple42!',
            'registration_form[agreeTerms]' => true,
        ]);

        // Ensure the response redirects after submitting the form, the user exists, and is not verified
        self::assertResponseRedirects('/');
        self::assertCount(1, $this->userRepository->findAll());
        self::assertFalse(($user = $this->userRepository->findAll()[0])->isVerified());

        // Ensure the verification email was sent
        // Use either assertQueuedEmailCount() || assertEmailCount() depending on your mailer setup
        // self::assertQueuedEmailCount(1);
        self::assertEmailCount(1);

        self::assertCount(1, $messages = $this->getMailerMessages());
        self::assertEmailAddressContains($messages[0], 'from', 'admin@example.com');
        self::assertEmailAddressContains($messages[0], 'to', 'me@example.com');
        self::assertEmailSubjectContains($messages[0], 'Please Confirm your Email');
        self::assertEmailTextBodyContains($messages[0], 'This link will expire in 1 hour.');

        // Login the new user
        $this->client->followRedirect();
        $this->client->loginUser($user);

        $resetLink = $this->extractVerificationLink();

        // "Click" the link and see if the user is verified
        $this->client->request('GET', $resetLink);
        self::assertResponseRedirects('/');
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'Your email address has been verified.');
        self::assertTrue(static::getContainer()->get(UserRepository::class)->findAll()[0]->isVerified());
    }

    public function testRegisterRejectsWeakPassword(): void
    {
        $this->client->request('GET', '/register');

        $this->client->submitForm('Register', [
            'registration_form[email]' => 'weak@example.com',
            'registration_form[plainPassword]' => 'password',
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'Your password should be at least 12 characters');
        self::assertCount(0, $this->userRepository->findAll());
        self::assertEmailCount(0);
    }

    public function testRegisterRejectsDuplicateEmail(): void
    {
        $this->client->request('GET', '/register');
        $this->submitRegistrationForm('duplicate@example.com');
        self::assertResponseRedirects('/');

        $this->client->request('GET', '/register');
        $this->submitRegistrationForm('duplicate@example.com');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'There is already an account with this email');
        self::assertCount(1, $this->userRepository->findAll());
        self::assertEmailCount(1);
    }

    public function testRegisterRejectsInvalidEmail(): void
    {
        $this->client->request('GET', '/register');
        $this->client->submitForm('Register', [
            'registration_form[email]' => 'not-an-email',
            'registration_form[plainPassword]' => 'CorrectHorseBatteryStaple42!',
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'This value is not a valid email address.');
        self::assertCount(0, $this->userRepository->findAll());
        self::assertEmailCount(0);
    }

    public function testRegisterRejectsEmptyPassword(): void
    {
        $this->client->request('GET', '/register');
        $this->client->submitForm('Register', [
            'registration_form[email]' => 'empty-password@example.com',
            'registration_form[plainPassword]' => '',
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'Please enter a password');
        self::assertCount(0, $this->userRepository->findAll());
        self::assertEmailCount(0);
    }

    public function testRegisterRejectsShortPassword(): void
    {
        $this->client->request('GET', '/register');
        $this->client->submitForm('Register', [
            'registration_form[email]' => 'short-password@example.com',
            'registration_form[plainPassword]' => 'Elephant1',
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'Your password should be at least 12 characters');
        self::assertCount(0, $this->userRepository->findAll());
        self::assertEmailCount(0);
    }

    public function testRegisterRejectsUncheckedTerms(): void
    {
        $this->client->request('GET', '/register');
        $this->client->submitForm('Register', [
            'registration_form[email]' => 'unchecked-terms@example.com',
            'registration_form[plainPassword]' => 'CorrectHorseBatteryStaple42!',
            'registration_form[agreeTerms]' => false,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'You should agree to our terms.');
        self::assertCount(0, $this->userRepository->findAll());
        self::assertEmailCount(0);
    }

    private function submitRegistrationForm(string $email): void
    {
        $this->client->submitForm('Register', [
            'registration_form[email]' => $email,
            'registration_form[plainPassword]' => 'CorrectHorseBatteryStaple42!',
            'registration_form[agreeTerms]' => true,
        ]);
    }

    private function extractVerificationLink(): string
    {
        $templatedEmail = $this->getMailerMessage(0);
        self::assertInstanceOf(TemplatedEmail::class, $templatedEmail);

        $messageBody = $templatedEmail->getHtmlBody();
        self::assertIsString($messageBody);

        $baseUri = static::getContainer()->getParameter('router.request_context.base_url');

        preg_match(\sprintf('#(%s/verify/email.+)">#', preg_quote($baseUri, '#')), $messageBody, $resetLink);
        self::assertArrayHasKey(1, $resetLink);

        return $resetLink[1];
    }
}
