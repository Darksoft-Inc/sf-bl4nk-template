<?php

namespace App\Tests\Controller;

use App\Entity\Map;
use App\Entity\User;
use App\Reference\ReferenceRegistry;
use App\Tests\ResetDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Translation\DataCollector\TranslationDataCollector;
use Symfony\Component\Translation\DataCollectorTranslator;

/**
 * Every string the pages translate must exist in a catalog: a template or a
 * widget calling the translator with an undefined key renders the raw key,
 * and the profiler reports it as a missing translation. This test walks the
 * page surface and fails naming the page and the undefined keys.
 */
final class TranslatedPagesTest extends WebTestCase
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

    public function testPublicPagesTranslateFully(): void
    {
        $map = new Map('Guard world', new \DateTimeImmutable('now'));
        $em = static::getContainer()->get('doctrine')->getManager();
        $em->persist($map);
        $em->flush();

        $this->assertPagesTranslateFully([
            '/',
            '/announcements',
            '/maps',
            '/maps/'.$map->getId(),
            '/docs/welcome',
            '/docs/terms',
            '/register',
            '/login',
            '/reset-password',
        ]);
    }

    public function testReferencePagesTranslateFully(): void
    {
        $registry = static::getContainer()->get(ReferenceRegistry::class);

        // The page metadata deliberately renders as literal text, as
        // docs/DECISIONS.md records. Only those literals may be missing;
        // the chrome around the pages still has to come from the catalog.
        $allowed = [];
        foreach ($registry->grouped() as $pages) {
            foreach ($pages as $page) {
                $allowed[] = $page->group;
                $allowed[] = $page->title;
                $allowed[] = $page->summary;
                foreach ($page->details as $detail) {
                    $allowed[] = $detail->label;
                    $allowed[] = $detail->text;
                }
            }
        }

        $this->assertPagesTranslateFully(['/reference', '/reference/login-audit-retention', '/reference/shared-files'], $allowed);
    }

    public function testMemberPagesTranslateFully(): void
    {
        $this->client->loginUser($this->createUser('member@example.com'));

        $this->assertPagesTranslateFully(['/profile', '/shared', '/shared/new']);
    }

    public function testAdminPagesTranslateFully(): void
    {
        $map = new Map('Guard world', new \DateTimeImmutable('now'));
        $em = static::getContainer()->get('doctrine')->getManager();
        $em->persist($map);
        $em->flush();

        $this->client->loginUser($this->createUser('admin@example.com', admin: true));

        $this->assertPagesTranslateFully([
            '/admin/users',
            '/admin/announcements',
            '/admin/announcements/new',
            '/admin/maps',
            '/admin/maps/images',
            '/admin/maps/'.$map->getId().'/edit',
            '/admin/login-audit',
            '/admin/maintenance/uploads',
            '/admin/maintenance/cleanup',
        ]);
    }

    /**
     * @param list<string> $urls
     * @param list<string> $allowedMissing literal strings that render as themselves by design
     */
    private function assertPagesTranslateFully(array $urls, array $allowedMissing = []): void
    {
        foreach ($urls as $url) {
            $this->client->enableProfiler();
            $this->client->request('GET', $url);

            self::assertResponseIsSuccessful('GET '.$url);

            $profile = $this->client->getProfile();
            if (!$profile instanceof Profile) {
                self::fail('No profiler profile for '.$url);
            }

            $collector = $profile->getCollector('translation');
            \assert($collector instanceof TranslationDataCollector);

            $messages = $collector->getMessages();
            \assert(\is_array($messages));

            $missing = [];
            foreach ($messages as $message) {
                \assert(\is_array($message));

                $id = $message['id'] ?? null;
                if (!\is_string($id)) {
                    self::fail('A collected translation has no string id on '.$url);
                }

                if (DataCollectorTranslator::MESSAGE_MISSING === ($message['state'] ?? null) && !\in_array($id, $allowedMissing, true)) {
                    $missing[] = $id;
                }
            }

            self::assertSame([], $missing, 'GET '.$url.' renders missing translations.');
        }
    }

    private function createUser(string $email, bool $admin = false): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setIsVerified(true);
        if ($admin) {
            $user->setRoles(['ROLE_ADMIN']);
        }
        $user->setPassword($this->passwordHasher->hashPassword($user, 'guard-password'));

        $em = static::getContainer()->get('doctrine')->getManager();
        $em->persist($user);
        $em->flush();

        return $user;
    }
}
