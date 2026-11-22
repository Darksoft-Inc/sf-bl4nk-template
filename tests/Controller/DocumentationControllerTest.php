<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DocumentationControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testWelcomePageRenders(): void
    {
        $this->client->request('GET', '/docs/welcome');

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Welcome');
        self::assertSelectorTextContains('main h1', 'Welcome');

        // The navbar links the documentation pages.
        self::assertSelectorExists('.navbar a[href="/docs/welcome"]');
    }

    public function testTermsPageRendersItsPlaceholder(): void
    {
        $this->client->request('GET', '/docs/terms');

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Terms');
        self::assertSelectorTextContains('main h1', 'Terms');
        self::assertSelectorTextContains('.alert-warning', 'This page is a placeholder.');
    }

    public function testUnknownPageReturns404(): void
    {
        $this->client->request('GET', '/docs/unknown-page');

        self::assertResponseStatusCodeSame(404);
    }

    public function testLanguageOutsideEnabledLocalesFallsBackToTheDefaultLocale(): void
    {
        $this->client->request('GET', '/docs/welcome', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main h1', 'Welcome');
    }
}
