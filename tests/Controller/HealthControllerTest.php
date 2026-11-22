<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testHealthIsNotRegisteredOutsideDev(): void
    {
        // config/routes/health.yaml registers the route under when@dev:
        // the local tooling probes /health on the dev server, the test
        // environment has no use for it, and production stays closed.
        $this->client->request('GET', '/health');

        self::assertResponseStatusCodeSame(404);
    }
}
