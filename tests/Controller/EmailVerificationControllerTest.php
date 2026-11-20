<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\ResetDatabase;
use Doctrine\ORM\EntityManager;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EmailVerificationControllerTest extends WebTestCase
{
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        /** @var EntityManager $em */
        $em = static::getContainer()->get('doctrine')->getManager();

        $this->resetDatabase($em);
    }

    public function testVerifyWithoutIdRedirectsToRegister(): void
    {
        $this->client->request('GET', '/verify/email');

        self::assertResponseRedirects('/register');
    }

    public function testVerifyWithUnknownUserIdRedirectsToRegister(): void
    {
        $this->client->request('GET', '/verify/email?id=999999');

        self::assertResponseRedirects('/register');
    }

    public function testVerifyWithInvalidSignatureShowsErrorOnRegisterPage(): void
    {
        $this->client->request('GET', '/register');
        $this->client->submitForm('Register', [
            'registration_form[email]' => 'tampered@example.com',
            'registration_form[plainPassword]' => 'CorrectHorseBatteryStaple42!',
            'registration_form[agreeTerms]' => true,
        ]);

        $verificationLink = $this->extractVerificationLink();

        $this->client->request('GET', $verificationLink.'x');

        self::assertResponseRedirects('/register');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'The link to verify your email is invalid. Please request a new link.');
    }

    public function testVerifyLinkCanBeClickedAgainAfterVerification(): void
    {
        $this->client->request('GET', '/register');
        $this->client->submitForm('Register', [
            'registration_form[email]' => 'repeat-click@example.com',
            'registration_form[plainPassword]' => 'CorrectHorseBatteryStaple42!',
            'registration_form[agreeTerms]' => true,
        ]);

        $verificationLink = $this->extractVerificationLink();

        $this->client->request('GET', $verificationLink);
        self::assertResponseRedirects('/index');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Your email address has been verified.');

        // The link is still within its lifetime: repeated clicks report the
        // verified state instead of running the verification again.
        $this->client->request('GET', $verificationLink);
        self::assertResponseRedirects('/index');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Your email address has already been verified.');

        $this->client->request('GET', $verificationLink);
        self::assertResponseRedirects('/index');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Your email address has already been verified.');

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy(['email' => 'repeat-click@example.com']);
        self::assertInstanceOf(User::class, $user);
        self::assertTrue($user->isVerified());
    }

    private function extractVerificationLink(): string
    {
        $templatedEmail = $this->getMailerMessage(0);
        self::assertInstanceOf(TemplatedEmail::class, $templatedEmail);

        $messageBody = $templatedEmail->getHtmlBody();
        self::assertIsString($messageBody);

        $baseUri = static::getContainer()->getParameter('router.request_context.base_url');

        preg_match(\sprintf('#(%s/verify/email.+)">#', preg_quote($baseUri, '#')), $messageBody, $match);
        self::assertArrayHasKey(1, $match);

        return $match[1];
    }
}
