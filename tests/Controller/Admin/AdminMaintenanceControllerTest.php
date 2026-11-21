<?php

namespace App\Tests\Controller\Admin;

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

            $token = $crawler->filter('input[name="_csrf_token"]')->first()->attr('value');
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
