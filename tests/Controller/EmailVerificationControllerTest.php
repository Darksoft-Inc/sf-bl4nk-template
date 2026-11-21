<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\ResetDatabase;
use Doctrine\ORM\EntityManager;
use Symfony\Bridge\PhpUnit\Attribute\UseRecord;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[UseRecord('pwnedpasswords-ranges.har')]
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

    public function testVerifyWithExpiredSignatureReportsExpiredOrVerifiedState(): void
    {
        $this->client->request('GET', '/register');
        $this->client->submitForm('Register', [
            'registration_form[email]' => 'expired-link@example.com',
            'registration_form[plainPassword]' => 'CorrectHorseBatteryStaple42!',
            'registration_form[agreeTerms]' => true,
        ]);

        $expiredLink = $this->expiredVerificationLink();

        $this->client->request('GET', $expiredLink);
        self::assertResponseRedirects('/register');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'The link to verify your email has expired. Please request a new link.');

        // The same expired link clicked by a verified user reports the
        // verified state through the catch branch.
        $this->client->request('GET', $this->extractVerificationLink());
        self::assertResponseRedirects('/index');
        $this->client->followRedirect();

        $this->client->request('GET', $expiredLink);
        self::assertResponseRedirects('/index');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Your email address has already been verified.');

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy(['email' => 'expired-link@example.com']);
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

    /**
     * Rebuilds the verification link with a past "expires" and a fresh
     * signature. The bundle compares "expires" against time(), so a clock mock
     * cannot reach that check; the signer service signs the tampered URL the
     * same way it signs the original.
     */
    private function expiredVerificationLink(): string
    {
        $verificationLink = $this->extractVerificationLink();

        $queryPosition = strpos($verificationLink, '?');
        self::assertIsInt($queryPosition);

        parse_str(substr($verificationLink, $queryPosition + 1), $query);
        $query['expires'] = (string) (time() - 100);
        unset($query['signature'], $query['_expiration']);

        $signer = static::getContainer()->get('symfonycasts.verify_email.uri_signer');

        return $signer->sign(substr($verificationLink, 0, $queryPosition).'?'.http_build_query($query));
    }
}
