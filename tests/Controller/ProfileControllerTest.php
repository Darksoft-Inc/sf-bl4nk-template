<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\ResetDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\PhpUnit\Attribute\UseRecord;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[UseRecord('pwnedpasswords-ranges.har')]
final class ProfileControllerTest extends WebTestCase
{
    use ResetDatabase;
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get('doctrine.orm.entity_manager');
        $this->userRepository = $container->get(UserRepository::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $this->resetDatabase($this->entityManager);
    }

    public function testProfileRequiresLogin(): void
    {
        $this->client->request('GET', '/profile');

        self::assertResponseRedirects('/login');
    }

    public function testProfileShowsDetailsAndChangesPassword(): void
    {
        $user = $this->createUser('profile@example.com', 'CorrectHorseBatteryStaple42!', true);
        $this->client->loginUser($user);

        $this->client->request('GET', '/profile');

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Profile');
        self::assertSelectorTextContains('h1', 'Profile');
        self::assertSelectorTextContains('body', 'profile@example.com');
        self::assertSelectorTextContains('body', 'Verified');

        $this->client->submitForm('Change password', [
            'change_password_form[plainPassword][first]' => 'AnotherStrongPassword42!',
            'change_password_form[plainPassword][second]' => 'AnotherStrongPassword42!',
        ]);

        self::assertResponseRedirects('/profile');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Your password has been changed.');

        $updatedUser = $this->userRepository->findOneBy(['email' => 'profile@example.com']);
        self::assertInstanceOf(User::class, $updatedUser);
        self::assertTrue($this->passwordHasher->isPasswordValid($updatedUser, 'AnotherStrongPassword42!'));
    }

    public function testProfileUploadsAnAvatar(): void
    {
        $user = $this->createUser('avatar@example.com', 'CorrectHorseBatteryStaple42!', true);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/profile');
        self::assertResponseIsSuccessful();

        $token = $crawler->filter('#avatar_form__token')->attr('value');
        self::assertIsString($token);

        $pngPath = tempnam(sys_get_temp_dir(), 'avatar-fixture').'.png';
        file_put_contents($pngPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        try {
            $this->client->request('POST', '/profile', [
                'avatar_form' => ['_token' => $token],
            ], [
                'avatar_form' => ['avatar' => new UploadedFile($pngPath, 'avatar.png', 'image/png', null, true)],
            ]);

            self::assertResponseRedirects('/profile');
            $this->client->followRedirect();
            self::assertSelectorTextContains('.alert-success', 'Your avatar has been updated.');
            self::assertSelectorExists('img.avatar');
            self::assertSelectorExists('[data-controller="symfony--ux-dropzone--dropzone"]');

            $updatedUser = $this->userRepository->findOneBy(['email' => 'avatar@example.com']);
            self::assertInstanceOf(User::class, $updatedUser);
            self::assertNotNull($updatedUser->getAvatarFilename());

            $avatarPath = \dirname(__DIR__, 2).'/public/uploads/avatars/'.$updatedUser->getAvatarFilename();
            self::assertFileExists($avatarPath);

            unlink($avatarPath);
        } finally {
            // The upload moved the file, so it is already gone here.
            if (is_file($pngPath)) {
                unlink($pngPath);
            }
        }
    }

    public function testProfileRejectsATextFileAsAvatar(): void
    {
        $user = $this->createUser('avatar-text@example.com', 'CorrectHorseBatteryStaple42!', true);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/profile');
        self::assertResponseIsSuccessful();

        $token = $crawler->filter('#avatar_form__token')->attr('value');
        self::assertIsString($token);

        $txtPath = tempnam(sys_get_temp_dir(), 'avatar-fixture').'.txt';
        file_put_contents($txtPath, 'plain text, not an image');

        try {
            $this->client->request('POST', '/profile', [
                'avatar_form' => ['_token' => $token],
            ], [
                'avatar_form' => ['avatar' => new UploadedFile($txtPath, 'notes.txt', 'text/plain', null, true)],
            ]);

            self::assertResponseStatusCodeSame(422);
            self::assertSelectorTextContains('.invalid-feedback', 'The avatar must be a PNG, JPEG, WebP, or GIF image.');

            $updatedUser = $this->userRepository->findOneBy(['email' => 'avatar-text@example.com']);
            self::assertInstanceOf(User::class, $updatedUser);
            self::assertNull($updatedUser->getAvatarFilename());
        } finally {
            unlink($txtPath);
        }
    }

    private function createUser(string $email, string $password, bool $verified): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setIsVerified($verified)
        ;
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
