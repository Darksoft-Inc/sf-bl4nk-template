<?php

namespace App\Tests\Controller\Admin;

use App\Entity\SharedUpload;
use App\Entity\SharedUploadType;
use App\Entity\User;
use App\Tests\ResetDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminMaintenanceControllerTest extends WebTestCase
{
    use ResetDatabase;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;
    private string $avatarDir;
    private string $textDir;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        $this->em = $em;

        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

        /** @var string $avatarDir */
        $avatarDir = $container->getParameter('app.avatar_dir');
        $this->avatarDir = $avatarDir;

        /** @var string $textDir */
        $textDir = $container->getParameter('app.shared_text_dir');
        $this->textDir = $textDir;

        $this->resetDatabase($this->em);
    }

    public function testAnonymousVisitorsAreRedirectedToLogin(): void
    {
        $this->client->request('GET', '/admin/maintenance/uploads');

        self::assertResponseRedirects('/login');
    }

    public function testUsersWithoutAdminRoleAreDenied(): void
    {
        $this->client->loginUser($this->createUser('plain@example.com', false));

        $this->client->request('GET', '/admin/maintenance/uploads');

        self::assertResponseStatusCodeSame(403);
    }

    public function testUploadsListShowsFilesPerKind(): void
    {
        $filename = $this->writeAvatarFixture('list');
        $user = $this->createUser('owner@example.com', false);
        $user->setAvatarFilename($filename);
        $this->em->flush();

        try {
            $this->client->loginUser($this->createUser('admin@example.com', true));

            $crawler = $this->client->request('GET', '/admin/maintenance/uploads');

            self::assertResponseIsSuccessful();
            self::assertPageTitleContains('Uploads');
            self::assertSelectorTextContains('.upload-row', $filename);
            self::assertSelectorTextContains('.upload-row', 'owner@example.com');
        } finally {
            unlink($this->avatarDir.'/'.$filename);
        }
    }

    public function testRemovingAFileClearsTheOwnerField(): void
    {
        $filename = $this->writeAvatarFixture('remove');
        $user = $this->createUser('owner@example.com', false);
        $user->setAvatarFilename($filename);
        $this->em->flush();

        try {
            $this->client->loginUser($this->createUser('admin@example.com', true));

            $crawler = $this->client->request('GET', '/admin/maintenance/uploads');
            self::assertResponseIsSuccessful();

            // The navbar logout form carries its own CSRF token: scope the
            // filter to this row's remove form, not the whole page.
            $token = $crawler
                ->filter(sprintf('form[action$="/avatar/%s/remove"] input[name="_csrf_token"]', $filename))
                ->attr('value');
            self::assertIsString($token);

            $this->client->request('POST', '/admin/maintenance/uploads/avatar/'.$filename.'/remove', [
                '_csrf_token' => $token,
            ]);

            self::assertResponseRedirects('/admin/maintenance/uploads');
            $this->client->followRedirect();
            self::assertSelectorTextContains('.alert-success', 'The file has been removed.');

            self::assertFileDoesNotExist($this->avatarDir.'/'.$filename);

            $updated = $this->em->createQuery('SELECT u FROM App\Entity\User u WHERE u.email = :email')
                ->setParameter('email', 'owner@example.com')
                ->getSingleResult();
            \assert($updated instanceof User);
            self::assertNull($updated->getAvatarFilename());
        } finally {
            if (is_file($this->avatarDir.'/'.$filename)) {
                unlink($this->avatarDir.'/'.$filename);
            }
        }
    }

    public function testRemovingASharedFileDeletesTheRow(): void
    {
        $owner = $this->createUser('shared-owner@example.com', false);

        // Matches the kind rule: 32 hex characters, like the stored names.
        $filename = md5('shared-remove').'.txt';

        if (!is_dir($this->textDir)) {
            mkdir($this->textDir, 0777, true);
        }

        file_put_contents($this->textDir.'/'.$filename, 'fixture');

        $upload = new SharedUpload(
            $owner,
            SharedUploadType::Text,
            $filename,
            'notes.txt',
            \strlen('fixture'),
            new \DateTimeImmutable('2026-11-22 10:00:00'),
        );
        $this->em->persist($upload);
        $this->em->flush();

        try {
            $this->client->loginUser($this->createUser('admin@example.com', true));

            $crawler = $this->client->request('GET', '/admin/maintenance/uploads');
            self::assertResponseIsSuccessful();

            $headings = $crawler->filter('h2')->extract(['_text']);
            self::assertContains('Shared text files', $headings);

            // The kinds of other directories list their own files, so the
            // assertions target this row through its remove form.
            $row = $crawler->filterXPath(\sprintf('//tr[.//form[contains(@action, "/shared_text/%s/remove")]]', $filename));
            self::assertCount(1, $row);
            self::assertStringContainsString('shared-owner@example.com', $row->text());
            self::assertStringContainsString($filename, $row->text());

            // The navbar logout form carries its own CSRF token: scope
            // the filter to this row's remove form, not the whole page.
            $token = $crawler
                ->filter(sprintf('form[action$="/shared_text/%s/remove"] input[name="_csrf_token"]', $filename))
                ->attr('value');
            self::assertIsString($token);

            $this->client->request('POST', '/admin/maintenance/uploads/shared_text/'.$filename.'/remove', [
                '_csrf_token' => $token,
            ]);

            self::assertResponseRedirects('/admin/maintenance/uploads');
            $this->client->followRedirect();
            self::assertSelectorTextContains('.alert-success', 'The file has been removed.');

            self::assertFileDoesNotExist($this->textDir.'/'.$filename);
            self::assertNull($this->em->find(SharedUpload::class, $upload->getId()));
        } finally {
            if (is_file($this->textDir.'/'.$filename)) {
                unlink($this->textDir.'/'.$filename);
            }
        }
    }

    private function writeAvatarFixture(string $seed): string
    {
        // Matches the kind rule: 32 hex characters, like the upload names.
        $filename = md5($seed).'.png';

        if (!is_dir($this->avatarDir)) {
            mkdir($this->avatarDir, 0777, true);
        }

        file_put_contents($this->avatarDir.'/'.$filename, 'fixture');

        return $filename;
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
