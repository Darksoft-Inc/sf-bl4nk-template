<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Map;
use App\Entity\User;
use App\Tests\ResetDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminMapControllerTest extends WebTestCase
{
    use ResetDatabase;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;
    private string $mapImageDir;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        $this->em = $em;

        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

        /** @var string $mapImageDir */
        $mapImageDir = $container->getParameter('app.map_image_dir');
        $this->mapImageDir = $mapImageDir;

        $this->resetDatabase($this->em);
    }

    public function testAnonymousVisitorsAreRedirectedToLogin(): void
    {
        $this->client->request('GET', '/admin/maps');

        self::assertResponseRedirects('/login');
    }

    public function testUsersWithoutAdminRoleAreDenied(): void
    {
        $this->client->loginUser($this->createUser('plain@example.com', false));

        $this->client->request('GET', '/admin/maps');

        self::assertResponseStatusCodeSame(403);
    }

    public function testIndexListsTheMaps(): void
    {
        $map = new Map('A known world', new \DateTimeImmutable('now'));
        $this->em->persist($map);
        $this->em->flush();

        $this->client->loginUser($this->createUser('admin@example.com', true));

        $crawler = $this->client->request('GET', '/admin/maps');

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Maps');
        self::assertSelectorTextContains('.map-row', 'A known world');
        self::assertCount(1, $crawler->filter('.map-row'));
    }

    public function testNewCreatesAMapWithTheChosenImage(): void
    {
        $filename = $this->writeImageFixture('known-world');

        try {
            $this->client->loginUser($this->createUser('admin@example.com', true));

            $this->client->request('GET', '/admin/maps/new');
            self::assertResponseIsSuccessful();

            $this->client->submitForm('Save', [
                'map_form[name]' => 'A known world',
                'map_form[image]' => $filename,
                'map_form[minZoom]' => '1',
                'map_form[maxZoom]' => '3',
            ]);

            $map = $this->em->createQuery('SELECT m FROM App\Entity\Map m')->getSingleResult();
            \assert($map instanceof Map);
            self::assertSame('A known world', $map->getName());
            self::assertSame($filename, $map->getImageFilename());
            // The fixture is a real 1x1 PNG: its pixel size defines the
            // coordinate space of the map.
            self::assertSame(1, $map->getImageWidth());
            self::assertSame(1, $map->getImageHeight());
            self::assertSame(1, $map->getMinZoom());
            self::assertSame(3, $map->getMaxZoom());

            self::assertResponseRedirects('/admin/maps/'.$map->getId().'/edit');
        } finally {
            $this->removeImageFixture($filename);
        }
    }

    public function testNewCreatesAMapWithoutAnImage(): void
    {
        $this->client->loginUser($this->createUser('admin@example.com', true));

        $this->client->request('GET', '/admin/maps/new');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Save', [
            'map_form[name]' => 'A world without a background',
            'map_form[image]' => '',
            'map_form[minZoom]' => '0',
            'map_form[maxZoom]' => '4',
        ]);

        $map = $this->em->createQuery('SELECT m FROM App\Entity\Map m')->getSingleResult();
        \assert($map instanceof Map);
        self::assertNull($map->getImageFilename());
    }

    public function testNewRejectsAMaximumZoomBelowTheMinimum(): void
    {
        $this->client->loginUser($this->createUser('admin@example.com', true));

        $this->client->request('GET', '/admin/maps/new');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Save', [
            'map_form[name]' => 'A zoomed world',
            'map_form[image]' => '',
            'map_form[minZoom]' => '3',
            'map_form[maxZoom]' => '1',
        ]);

        // The framework renders an invalid submitted form with 422.
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'The maximum zoom cannot be lower than the minimum zoom.');

        self::assertSame(0, $this->em->createQuery('SELECT COUNT(m) FROM App\Entity\Map m')->getSingleScalarResult());
    }

    public function testEditKeepsTheZoomsWhenTheyAreSubmittedEmpty(): void
    {
        // The zoom fields are required in the browser, but a crafted post
        // can submit them empty. The model setters reject null, and the
        // mapper writes before validation, so the empty_data option makes
        // an empty submission keep the current value instead of crashing.
        $map = new Map('A known world', new \DateTimeImmutable('now'));
        $map->setMinZoom(1);
        $map->setMaxZoom(3);
        $this->em->persist($map);
        $this->em->flush();

        $this->client->loginUser($this->createUser('admin@example.com', true));

        $crawler = $this->client->request('GET', '/admin/maps/'.$map->getId().'/edit');
        self::assertResponseIsSuccessful();
        self::assertNotNull($crawler->filter('#map_form_minZoom')->attr('required'));

        // The section headers translate: their keys once missed from the
        // catalog and the page printed the raw keys instead.
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Route types', $html);
        self::assertStringContainsString('Points', $html);
        self::assertStringContainsString('Routes', $html);
        self::assertStringNotContainsString('app.admin.map.route_types', $html);
        self::assertStringNotContainsString('app.admin.map.points', $html);
        self::assertStringNotContainsString('app.admin.map.routes', $html);

        $token = $crawler->filter('#map_form__token')->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', '/admin/maps/'.$map->getId().'/edit', [
            'map_form' => ['_token' => $token, 'name' => 'A renamed world', 'image' => '', 'minZoom' => '', 'maxZoom' => ''],
        ]);

        self::assertResponseRedirects('/admin/maps/'.$map->getId().'/edit');

        $this->em->clear();

        $updated = $this->em->find(Map::class, $map->getId());
        \assert($updated instanceof Map);
        self::assertSame('A renamed world', $updated->getName());
        self::assertSame(1, $updated->getMinZoom());
        self::assertSame(3, $updated->getMaxZoom());
    }

    public function testEditChangesTheParametersAndUnsetsTheImage(): void
    {
        $filename = $this->writeImageFixture('known-world');
        $map = new Map('A known world', new \DateTimeImmutable('now'));
        $map->setImageFilename($filename);
        $map->setImageSize(1, 1);
        $this->em->persist($map);
        $this->em->flush();

        try {
            $this->client->loginUser($this->createUser('admin@example.com', true));

            $this->client->request('GET', '/admin/maps/'.$map->getId().'/edit');
            self::assertResponseIsSuccessful();

            $this->client->submitForm('Save', [
                'map_form[name]' => 'A renamed world',
                'map_form[image]' => '',
                'map_form[minZoom]' => '0',
                'map_form[maxZoom]' => '2',
            ]);

            self::assertResponseRedirects('/admin/maps/'.$map->getId().'/edit');

            // The identity map of this test's manager holds the instance
            // from before the submit; clear it to read the stored row.
            $this->em->clear();

            $updated = $this->em->find(Map::class, $map->getId());
            \assert($updated instanceof Map);
            self::assertSame('A renamed world', $updated->getName());
            self::assertNull($updated->getImageFilename());
            // The space survives an unset image, so the points keep their
            // positions for the next image.
            self::assertSame(1, $updated->getImageWidth());
            self::assertSame(1, $updated->getImageHeight());
            self::assertSame(2, $updated->getMaxZoom());

            self::assertFileExists($this->mapImageDir.'/'.$filename);
        } finally {
            $this->removeImageFixture($filename);
        }
    }

    public function testDeleteRemovesTheMap(): void
    {
        $map = new Map('A doomed world', new \DateTimeImmutable('now'));
        $this->em->persist($map);
        $this->em->flush();

        $this->client->loginUser($this->createUser('admin@example.com', true));

        $crawler = $this->client->request('GET', '/admin/maps');
        $token = $crawler->filter('.map-row input[name="_csrf_token"]')->first()->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', '/admin/maps/'.$map->getId().'/delete', [
            '_csrf_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/maps');
        self::assertSame(0, $this->em->createQuery('SELECT COUNT(m) FROM App\Entity\Map m')->getSingleScalarResult());
    }

    public function testImagesPageListsThePoolWithItsUsers(): void
    {
        $filename = $this->writeImageFixture('pool');
        $map = new Map('A known world', new \DateTimeImmutable('now'));
        $map->setImageFilename($filename);
        $map->setImageSize(1, 1);
        $this->em->persist($map);
        $this->em->flush();

        try {
            $this->client->loginUser($this->createUser('admin@example.com', true));

            $crawler = $this->client->request('GET', '/admin/maps/images');

            self::assertResponseIsSuccessful();
            $fixtureRow = $crawler->filter('.map-image-row')->reduce(
                static fn (\Symfony\Component\DomCrawler\Crawler $row): bool => str_contains($row->text(), $filename),
            );
            self::assertCount(1, $fixtureRow);
            self::assertSelectorTextContains('.map-image-row', 'A known world');
        } finally {
            $this->removeImageFixture($filename);
        }
    }

    public function testImageUploadStoresTheFileInThePool(): void
    {
        $pngPath = tempnam(sys_get_temp_dir(), 'map-fixture').'.png';
        file_put_contents($pngPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        $poolBefore = $this->poolFilenames();

        try {
            $this->client->loginUser($this->createUser('admin@example.com', true));

            $crawler = $this->client->request('GET', '/admin/maps/images');
            $token = $crawler->filter('#map_image_form__token')->attr('value');
            self::assertIsString($token);

            $this->client->request('POST', '/admin/maps/images/upload', [
                'map_image_form' => ['_token' => $token],
            ], [
                'map_image_form' => ['image' => new UploadedFile($pngPath, 'world.png', 'image/png', null, true)],
            ]);

            self::assertResponseRedirects('/admin/maps/images');
            $this->client->followRedirect();
            self::assertSelectorTextContains('.alert-success', 'The image has been uploaded.');

            // The pool is runtime data an operator may already use, so the
            // test checks what the upload added rather than the whole pool.
            $newFiles = array_values(array_diff($this->poolFilenames(), $poolBefore));
            self::assertCount(1, $newFiles);
            self::assertMatchesRegularExpression('/^[a-f0-9]{32}\.png$/', $newFiles[0]);
        } finally {
            $this->removePoolFiles(array_values(array_diff($this->poolFilenames(), $poolBefore)));

            // The upload moved the file, so it is already gone here.
            if (is_file($pngPath)) {
                unlink($pngPath);
            }
        }
    }

    public function testImageUploadRejectsAPlainFile(): void
    {
        $txtPath = tempnam(sys_get_temp_dir(), 'map-fixture').'.txt';
        file_put_contents($txtPath, 'plain text, not an image');

        try {
            $this->client->loginUser($this->createUser('admin@example.com', true));

            $crawler = $this->client->request('GET', '/admin/maps/images');
            $token = $crawler->filter('#map_image_form__token')->attr('value');
            self::assertIsString($token);

            $this->client->request('POST', '/admin/maps/images/upload', [
                'map_image_form' => ['_token' => $token],
            ], [
                'map_image_form' => ['image' => new UploadedFile($txtPath, 'notes.txt', 'text/plain', null, true)],
            ]);

            self::assertResponseStatusCodeSame(422);
            self::assertSelectorTextContains('.invalid-feedback', 'The image must be a PNG, JPEG, WebP, or GIF image.');

            self::assertFileDoesNotExist($this->mapImageDir.'/notes.txt');
        } finally {
            unlink($txtPath);
        }
    }

    public function testRemovingAnImageUsedByAMapIsRefused(): void
    {
        $filename = $this->writeImageFixture('in-use');
        $map = new Map('A known world', new \DateTimeImmutable('now'));
        $map->setImageFilename($filename);
        $map->setImageSize(1, 1);
        $this->em->persist($map);
        $this->em->flush();

        try {
            $this->client->loginUser($this->createUser('admin@example.com', true));

            $crawler = $this->client->request('GET', '/admin/maintenance/uploads');
            self::assertResponseIsSuccessful();

            // The pool may hold other files: the token comes from the row
            // of the fixture, not from the first row of the page.
            $fixtureRow = $crawler->filter('.upload-row')->reduce(
                static fn (\Symfony\Component\DomCrawler\Crawler $row): bool => str_contains($row->text(), $filename),
            );
            $token = $fixtureRow->filter('input[name="_csrf_token"]')->attr('value');
            self::assertIsString($token);

            $this->client->request('POST', '/admin/maintenance/uploads/map_image/'.$filename.'/remove', [
                '_csrf_token' => $token,
            ]);

            self::assertResponseRedirects('/admin/maintenance/uploads');
            $this->client->followRedirect();
            self::assertSelectorTextContains('.alert-danger', 'The file is used and cannot be removed.');

            self::assertFileExists($this->mapImageDir.'/'.$filename);

            // The identity map of this test's manager holds the instance
            // from before the submit; clear it to read the stored row.
            $this->em->clear();

            $updated = $this->em->find(Map::class, $map->getId());
            \assert($updated instanceof Map);
            self::assertSame($filename, $updated->getImageFilename());
        } finally {
            $this->removeImageFixture($filename);
        }
    }

    public function testRemovingAnUnusedImageDeletesTheFile(): void
    {
        $filename = $this->writeImageFixture('unused');

        try {
            $this->client->loginUser($this->createUser('admin@example.com', true));

            $crawler = $this->client->request('GET', '/admin/maintenance/uploads');

            $fixtureRow = $crawler->filter('.upload-row')->reduce(
                static fn (\Symfony\Component\DomCrawler\Crawler $row): bool => str_contains($row->text(), $filename),
            );
            $token = $fixtureRow->filter('input[name="_csrf_token"]')->attr('value');
            self::assertIsString($token);

            $this->client->request('POST', '/admin/maintenance/uploads/map_image/'.$filename.'/remove', [
                '_csrf_token' => $token,
            ]);

            self::assertResponseRedirects('/admin/maintenance/uploads');
            $this->client->followRedirect();
            self::assertSelectorTextContains('.alert-success', 'The file has been removed.');

            self::assertFileDoesNotExist($this->mapImageDir.'/'.$filename);
        } finally {
            $this->removeImageFixture($filename);
        }
    }

    /**
     * A pre-existing image: the file sits in the pool directory the way an
     * operator would drop it, without an upload.
     */
    private function writeImageFixture(string $seed): string
    {
        if (!is_dir($this->mapImageDir)) {
            mkdir($this->mapImageDir, 0777, true);
        }

        $filename = md5($seed).'.png';
        file_put_contents(
            $this->mapImageDir.'/'.$filename,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
        );

        return $filename;
    }

    private function removeImageFixture(string $filename): void
    {
        if (is_file($this->mapImageDir.'/'.$filename)) {
            unlink($this->mapImageDir.'/'.$filename);
        }
    }

    /**
     * @return list<string>
     */
    private function poolFilenames(): array
    {
        if (!is_dir($this->mapImageDir)) {
            return [];
        }

        return array_values(array_filter(
            scandir($this->mapImageDir) ?: [],
            static fn (string $name): bool => !str_starts_with($name, '.'),
        ));
    }

    /**
     * @param list<string> $filenames
     */
    private function removePoolFiles(array $filenames): void
    {
        foreach ($filenames as $filename) {
            $this->removeImageFixture($filename);
        }
    }

    private function createUser(string $email, bool $admin): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setIsVerified(true)
            ->setRoles($admin ? ['ROLE_ADMIN'] : [])
        ;
        $user->setPassword($this->passwordHasher->hashPassword($user, 'CorrectHorseBatteryStaple42!'));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
