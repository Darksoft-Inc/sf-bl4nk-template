<?php

namespace App\Tests\Controller;

use App\Entity\SharedUpload;
use App\Entity\SharedUploadType;
use App\Entity\User;
use App\Repository\SharedUploadRepository;
use App\Tests\ResetDatabase;
use App\Uploads\SharedUploadStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SharedControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use ResetDatabase;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private SharedUploadRepository $uploadRepository;
    private UserPasswordHasherInterface $passwordHasher;
    private string $imageDir;
    private string $textDir;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get('doctrine.orm.entity_manager');
        $this->uploadRepository = $container->get(SharedUploadRepository::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

        /** @var string $imageDir */
        $imageDir = $container->getParameter('app.shared_image_dir');
        $this->imageDir = $imageDir;

        /** @var string $textDir */
        $textDir = $container->getParameter('app.shared_text_dir');
        $this->textDir = $textDir;

        $this->resetDatabase($this->entityManager);
    }

    public function testTheSectionRequiresLogin(): void
    {
        $this->client->request('GET', '/shared');
        self::assertResponseRedirects('/login');

        $this->client->request('GET', '/shared/new');
        self::assertResponseRedirects('/login');

        $this->client->request('GET', '/shared/1');
        self::assertResponseRedirects('/login');

        $this->client->request('POST', '/shared/1/delete');
        self::assertResponseRedirects('/login');
    }

    public function testTheIndexShowsTheEmptyState(): void
    {
        $this->client->loginUser($this->createUser('shared-empty@example.com', true));

        $this->client->request('GET', '/shared');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Shared files');
        self::assertSelectorTextContains('.shared-empty', 'No files shared yet.');
    }

    public function testAMemberSharesATextFile(): void
    {
        self::mockTime('2026-11-22 10:00:00');

        $user = $this->createUser('shared-text@example.com', true);
        $this->client->loginUser($user);

        $token = $this->uploadToken();
        $txtPath = tempnam(sys_get_temp_dir(), 'shared-fixture').'.txt';
        file_put_contents($txtPath, "line one\nline two <b>not bold</b>");

        $upload = null;
        $storedPath = null;

        try {
            $this->client->request('POST', '/shared/new', [
                'shared_upload_form' => ['_token' => $token],
            ], [
                'shared_upload_form' => ['file' => new UploadedFile($txtPath, 'notes.txt', 'text/plain', null, true)],
            ]);

            self::assertResponseRedirects('/shared');
            $this->client->followRedirect();
            self::assertSelectorTextContains('.alert-success', 'The file has been shared.');
            self::assertSelectorTextContains('.shared-row', 'notes.txt');

            $upload = $this->uploadRepository->findOneBy(['originalName' => 'notes.txt']);
            self::assertInstanceOf(SharedUpload::class, $upload);
            self::assertSame(SharedUploadType::Text, $upload->getType());
            self::assertSame($user->getEmail(), $upload->getOwner()?->getEmail());
            self::assertMatchesRegularExpression(SharedUploadStorage::TEXT_FILENAME_PATTERN, $upload->getFilename());

            $storedPath = $this->textDir.'/'.$upload->getFilename();
            self::assertFileExists($storedPath);

            $this->client->request('GET', '/shared/'.$upload->getId());
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('pre.shared-text', 'line two <b>not bold</b>');
        } finally {
            $this->removeStoredFile($storedPath);
            $this->removeFixture($txtPath);
        }
    }

    public function testAMemberSharesAnImage(): void
    {
        $user = $this->createUser('shared-image@example.com', true);
        $this->client->loginUser($user);

        $token = $this->uploadToken();
        $pngPath = $this->pngFixture();

        $upload = null;
        $storedPath = null;

        try {
            $this->client->request('POST', '/shared/new', [
                'shared_upload_form' => ['_token' => $token],
            ], [
                'shared_upload_form' => ['file' => new UploadedFile($pngPath, 'picture.png', 'image/png', null, true)],
            ]);

            self::assertResponseRedirects('/shared');

            $upload = $this->uploadRepository->findOneBy(['originalName' => 'picture.png']);
            self::assertInstanceOf(SharedUpload::class, $upload);
            self::assertSame(SharedUploadType::Image, $upload->getType());
            self::assertMatchesRegularExpression(SharedUploadStorage::IMAGE_FILENAME_PATTERN, $upload->getFilename());

            $storedPath = $this->imageDir.'/'.$upload->getFilename();
            self::assertFileExists($storedPath);

            $crawler = $this->client->request('GET', '/shared/'.$upload->getId());
            self::assertResponseIsSuccessful();

            $src = $crawler->filter('img.shared-image')->attr('src');
            self::assertIsString($src);
            self::assertStringContainsString('/uploads/shared/images/', $src);
        } finally {
            $this->removeStoredFile($storedPath);
            $this->removeFixture($pngPath);
        }
    }

    public function testTheSniffedContentDecidesTheCategory(): void
    {
        $this->client->loginUser($this->createUser('shared-sniff@example.com', true));

        // PNG bytes under a .txt name: the content makes it an image,
        // the original name stays display data.
        $token = $this->uploadToken();
        $pngPath = $this->pngFixture();

        $upload = null;
        $storedPath = null;

        try {
            $this->client->request('POST', '/shared/new', [
                'shared_upload_form' => ['_token' => $token],
            ], [
                'shared_upload_form' => ['file' => new UploadedFile($pngPath, 'notes.txt', 'text/plain', null, true)],
            ]);

            self::assertResponseRedirects('/shared');

            $upload = $this->uploadRepository->findOneBy(['originalName' => 'notes.txt']);
            self::assertInstanceOf(SharedUpload::class, $upload);
            self::assertSame(SharedUploadType::Image, $upload->getType());

            $storedPath = $this->imageDir.'/'.$upload->getFilename();
            self::assertFileExists($storedPath);
        } finally {
            $this->removeStoredFile($storedPath);
            $this->removeFixture($pngPath);
        }
    }

    public function testUnsupportedContentIsRejected(): void
    {
        $this->client->loginUser($this->createUser('shared-binary@example.com', true));

        $token = $this->uploadToken();
        $binPath = tempnam(sys_get_temp_dir(), 'shared-fixture').'.bin';
        file_put_contents($binPath, "\x00\x01\x02\x03\xFF\xFE binary blob");

        try {
            $this->client->request('POST', '/shared/new', [
                'shared_upload_form' => ['_token' => $token],
            ], [
                'shared_upload_form' => ['file' => new UploadedFile($binPath, 'data.bin', 'application/octet-stream', null, true)],
            ]);

            self::assertResponseStatusCodeSame(422);
            self::assertSelectorTextContains('.invalid-feedback', 'The file must be a PNG, JPEG, WebP, or GIF image, or a plain-text file.');
            self::assertCount(0, $this->uploadRepository->findAll());
        } finally {
            $this->removeFixture($binPath);
        }
    }

    public function testTheListOrdersNewestFirst(): void
    {
        $this->client->loginUser($this->createUser('shared-order@example.com', true));

        $firstPath = tempnam(sys_get_temp_dir(), 'shared-fixture').'.txt';
        $secondPath = tempnam(sys_get_temp_dir(), 'shared-fixture').'.txt';
        file_put_contents($firstPath, 'first');
        file_put_contents($secondPath, 'second');

        $uploadOne = null;
        $uploadTwo = null;

        try {
            self::mockTime('2026-11-22 10:00:00');
            $this->client->request('POST', '/shared/new', [
                'shared_upload_form' => ['_token' => $this->uploadToken()],
            ], [
                'shared_upload_form' => ['file' => new UploadedFile($firstPath, 'first.txt', 'text/plain', null, true)],
            ]);

            self::mockTime('2026-11-22 11:00:00');
            $this->client->request('POST', '/shared/new', [
                'shared_upload_form' => ['_token' => $this->uploadToken()],
            ], [
                'shared_upload_form' => ['file' => new UploadedFile($secondPath, 'second.txt', 'text/plain', null, true)],
            ]);

            $crawler = $this->client->request('GET', '/shared');
            self::assertResponseIsSuccessful();

            $rows = $crawler->filter('tr.shared-row');
            self::assertCount(2, $rows);
            self::assertStringContainsString('second.txt', $rows->eq(0)->text());
            self::assertStringContainsString('first.txt', $rows->eq(1)->text());

            $uploadOne = $this->uploadRepository->findOneBy(['originalName' => 'first.txt']);
            $uploadTwo = $this->uploadRepository->findOneBy(['originalName' => 'second.txt']);
        } finally {
            $this->removeStoredFile(null !== $uploadOne ? $this->textDir.'/'.$uploadOne->getFilename() : null);
            $this->removeStoredFile(null !== $uploadTwo ? $this->textDir.'/'.$uploadTwo->getFilename() : null);
            $this->removeFixture($firstPath);
            $this->removeFixture($secondPath);
        }
    }

    public function testAnUnknownFileReturns404(): void
    {
        $this->client->loginUser($this->createUser('shared-404@example.com', true));

        $this->client->request('GET', '/shared/999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheListPaginatesAtTenRows(): void
    {
        $user = $this->createUser('shared-pages@example.com', true);
        $this->client->loginUser($user);

        $uploads = [];
        for ($i = 1; $i <= 11; ++$i) {
            $uploads[] = $this->storeTextUpload($user);
        }

        try {
            $crawler = $this->client->request('GET', '/shared');
            self::assertResponseIsSuccessful();
            self::assertCount(10, $crawler->filter('tr.shared-row'));

            $crawler = $this->client->request('GET', '/shared?page=2');
            self::assertResponseIsSuccessful();
            self::assertCount(1, $crawler->filter('tr.shared-row'));
        } finally {
            foreach ($uploads as $upload) {
                $this->removeStoredFile($this->textDir.'/'.$upload->getFilename());
            }
        }
    }

    public function testTheOwnerDeletesAnUpload(): void
    {
        $user = $this->createUser('shared-owner@example.com', true);
        $this->client->loginUser($user);

        $upload = $this->storeTextUpload($user);
        $storedPath = $this->textDir.'/'.$upload->getFilename();

        try {
            $crawler = $this->client->request('GET', '/shared/'.$upload->getId());
            self::assertResponseIsSuccessful();

            // The navbar logout form carries its own CSRF token: scope
            // the filter to this file's delete form, not the whole page.
            $token = $crawler
                ->filter(sprintf('form[action$="/shared/%d/delete"] input[name="_csrf_token"]', $upload->getId()))
                ->attr('value');
            self::assertIsString($token);

            $this->client->request('POST', '/shared/'.$upload->getId().'/delete', [
                '_csrf_token' => $token,
            ]);

            self::assertResponseRedirects('/shared');
            $this->client->followRedirect();
            self::assertSelectorTextContains('.alert-success', 'The shared file has been removed.');

            self::assertFileDoesNotExist($storedPath);
            self::assertNull($this->uploadRepository->find($upload->getId()));
        } finally {
            $this->removeStoredFile($storedPath);
        }
    }

    public function testAnotherMemberCannotDeleteAnUpload(): void
    {
        $owner = $this->createUser('shared-real-owner@example.com', true);
        $upload = $this->storeTextUpload($owner);
        $storedPath = $this->textDir.'/'.$upload->getFilename();

        try {
            $this->client->loginUser($this->createUser('shared-intruder@example.com', true));

            $this->client->request('POST', '/shared/'.$upload->getId().'/delete', [
                '_csrf_token' => 'any-token',
            ]);

            self::assertResponseStatusCodeSame(403);
            self::assertFileExists($storedPath);
            self::assertNotNull($this->uploadRepository->find($upload->getId()));
        } finally {
            $this->removeStoredFile($storedPath);
        }
    }

    public function testABadDeleteTokenChangesNothing(): void
    {
        $user = $this->createUser('shared-token@example.com', true);
        $this->client->loginUser($user);

        $upload = $this->storeTextUpload($user);
        $storedPath = $this->textDir.'/'.$upload->getFilename();

        try {
            $this->client->request('POST', '/shared/'.$upload->getId().'/delete', [
                '_csrf_token' => 'not-the-token',
            ]);

            self::assertResponseRedirects('/shared');
            $this->client->followRedirect();
            self::assertSelectorTextContains('.alert-danger', 'The removal was refused.');

            self::assertFileExists($storedPath);
            self::assertNotNull($this->uploadRepository->find($upload->getId()));
        } finally {
            $this->removeStoredFile($storedPath);
        }
    }

    private function uploadToken(): string
    {
        $crawler = $this->client->request('GET', '/shared/new');
        self::assertResponseIsSuccessful();

        $token = $crawler->filter('#shared_upload_form__token')->attr('value');
        self::assertIsString($token);

        return $token;
    }

    /**
     * Stores one text upload for the owner, without going through the
     * form, for the tests that only exercise the pages around a file.
     */
    private function storeTextUpload(User $owner): SharedUpload
    {
        $filename = bin2hex(random_bytes(16)).'.txt';

        if (!is_dir($this->textDir)) {
            mkdir($this->textDir, 0777, true);
        }

        file_put_contents($this->textDir.'/'.$filename, 'fixture content');

        $upload = new SharedUpload(
            $owner,
            SharedUploadType::Text,
            $filename,
            'fixture.txt',
            \strlen('fixture content'),
            new \DateTimeImmutable('2026-11-22 10:00:00'),
        );

        $this->entityManager->persist($upload);
        $this->entityManager->flush();

        return $upload;
    }

    private function pngFixture(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'shared-fixture').'.png';
        file_put_contents(
            $path,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
        );

        return $path;
    }

    private function removeStoredFile(?string $path): void
    {
        if (null !== $path && is_file($path)) {
            unlink($path);
        }
    }

    private function removeFixture(string $path): void
    {
        // The upload moved the file, so it may already be gone here.
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function createUser(string $email, bool $verified): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setIsVerified($verified)
        ;
        $user->setPassword($this->passwordHasher->hashPassword($user, 'CorrectHorseBatteryStaple42!'));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
