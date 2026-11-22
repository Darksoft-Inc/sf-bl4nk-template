<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The reference pages come from the Reference attributes of the real
 * services, so a booting kernel already proves the collected pages
 * pass the build validation. The pages read no database.
 */
final class ReferenceControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testTheIndexListsThePagesGrouped(): void
    {
        $crawler = $this->client->request('GET', '/reference');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Reference');

        $headings = $crawler->filter('h2')->extract(['_text']);
        self::assertContains('Account', $headings);
        self::assertContains('Files', $headings);

        $hrefs = $crawler->filter('a.reference-link')->extract(['href']);
        self::assertContains('/reference/login-audit-retention', $hrefs);
        self::assertContains('/reference/shared-files', $hrefs);
    }

    public function testTheSharedFilesPageRendersItsDetails(): void
    {
        $crawler = $this->client->request('GET', '/reference/shared-files');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Shared files');

        $labels = $crawler->filter('.reference-details dt')->extract(['_text']);
        self::assertContains('Accepted content', $labels);
        self::assertContains('Size limit', $labels);

        // The 2M comes from the MAX_SIZE constant of the service,
        // concatenated in the attribute argument itself.
        self::assertSelectorTextContains('.reference-details', 'At most 2M per file.');
    }

    public function testTheRetentionPageShowsTheParameterValue(): void
    {
        $this->client->request('GET', '/reference/login-audit-retention');

        self::assertResponseIsSuccessful();

        // The 90 comes from app.login_audit_retention_days, resolved by
        // the compiler pass when the container was built.
        self::assertSelectorTextContains('.reference-details', 'Rows older than 90 days are removed.');
    }

    public function testAnUnknownSlugReturns404(): void
    {
        $this->client->request('GET', '/reference/no-such-page');

        self::assertResponseStatusCodeSame(404);
    }

    public function testASlugOutsideTheRuleReturns404(): void
    {
        $this->client->request('GET', '/reference/Bad_Slug');

        self::assertResponseStatusCodeSame(404);
    }
}
