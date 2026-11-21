<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\ResetDatabase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class FlashMessagesTest extends WebTestCase
{
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $container = static::getContainer();

        $entityManager = $container->get('doctrine.orm.entity_manager');
        $this->resetDatabase($entityManager);

        $user = new User();
        $user->setEmail('flash-admin@example.com');
        $user->setIsVerified(true);
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($user, 'password'));
        $entityManager->persist($user);
        $entityManager->flush();

        $this->client->loginUser($user);
    }

    public function testSuccessFlashClosesOnItsOwn(): void
    {
        $crawler = $this->client->request('GET', '/admin/maintenance/cleanup');
        $token = $crawler->filter('form[action$="/cleanup/run"] input[name="_csrf_token"]')->attr('value');
        \assert(\is_string($token));

        $this->client->request('POST', '/admin/maintenance/cleanup/run', ['_csrf_token' => $token]);
        $this->client->followRedirect();

        $alert = $this->client->getCrawler()->filter('.alert-success');
        self::assertCount(1, $alert);
        self::assertSame('closeable', $alert->attr('data-controller'));
        self::assertSame('5000', $alert->attr('data-closeable-auto-close-value'));
        self::assertSame('status', $alert->attr('role'));
        self::assertSame('click->closeable#close', $alert->filter('button.btn-close')->first()->attr('data-action'));
        self::assertCount(1, $alert->filter('[data-closeable-target="timerbar"]'));
    }

    public function testErrorFlashStaysUntilDismissed(): void
    {
        $this->client->request('POST', '/admin/maintenance/cleanup/run', ['_csrf_token' => 'not-a-real-token']);
        $this->client->followRedirect();

        $alert = $this->client->getCrawler()->filter('.alert-danger');
        self::assertCount(1, $alert);
        self::assertSame('closeable', $alert->attr('data-controller'));
        self::assertSame('alert', $alert->attr('role'));
        self::assertNull($alert->attr('data-closeable-auto-close-value'));
        self::assertSame('click->closeable#close', $alert->filter('button.btn-close')->first()->attr('data-action'));
        self::assertCount(0, $alert->filter('[data-closeable-target="timerbar"]'));
    }
}
