<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Announcement;
use App\Entity\User;
use App\Repository\AnnouncementRepository;
use App\Tests\ResetDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminAnnouncementControllerTest extends WebTestCase
{
    use ResetDatabase;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        $this->em = $em;

        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $this->resetDatabase($this->em);
    }

    public function testAnonymousVisitorsAreRedirectedToLogin(): void
    {
        $this->client->request('GET', '/admin/announcements');

        self::assertResponseRedirects('/login');
    }

    public function testUsersWithoutAdminRoleAreDenied(): void
    {
        $this->client->loginUser($this->createUser('plain@example.com', false));

        $this->client->request('GET', '/admin/announcements');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCreatesAnAnnouncement(): void
    {
        $admin = $this->createUser('admin@example.com', true);
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/admin/announcements/new');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Save', [
            'announcement_form[title]' => 'Fresh news',
            'announcement_form[publishedAt]' => '2026-11-21T10:00',
            'announcement_form[content]' => "Line one.\nLine two.",
        ]);

        self::assertResponseRedirects('/admin/announcements');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'The announcement has been created.');
        self::assertSelectorTextContains('.announcement-title', 'Fresh news');

        $crawler = $this->client->request('GET', '/announcements');
        self::assertSelectorTextContains('body', 'Fresh news');

        /** @var AnnouncementRepository $repository */
        $repository = static::getContainer()->get(AnnouncementRepository::class);
        $announcement = $repository->findOneBy(['title' => 'Fresh news']);

        self::assertInstanceOf(Announcement::class, $announcement);
        self::assertSame('admin@example.com', $announcement->getAuthor()->getEmail());
        self::assertSame("Line one.\nLine two.", $announcement->getContent());
    }

    public function testAdminEditsAnAnnouncement(): void
    {
        $author = $this->createUser('author@example.com', false);
        $announcement = $this->createAnnouncement('Old title', 'Old content.', new \DateTimeImmutable('-1 day'), $author);
        $this->client->loginUser($this->createUser('admin@example.com', true));

        $crawler = $this->client->request('GET', '/admin/announcements/'.$announcement->getId().'/edit');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Save', [
            'announcement_form[title]' => 'New title',
        ]);

        self::assertResponseRedirects('/admin/announcements');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'The announcement has been updated.');
        self::assertSelectorTextContains('.announcement-title', 'New title');
    }

    public function testAnnouncementListIsPaginated(): void
    {
        $author = $this->createUser('author@example.com', false);
        $this->client->loginUser($this->createUser('admin@example.com', true));

        for ($i = 1; $i <= 12; ++$i) {
            $this->createAnnouncement('Announcement '.$i, 'Content.', new \DateTimeImmutable('-'.$i.' days'), $author);
        }

        $crawler = $this->client->request('GET', '/admin/announcements');
        self::assertResponseIsSuccessful();
        self::assertCount(10, $crawler->filter('.announcement-row'));
        self::assertSelectorExists('.pagination');

        $crawler = $this->client->request('GET', '/admin/announcements?page=2');
        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('.announcement-row'));
    }

    public function testAnnouncementSearchFiltersTheList(): void
    {
        $author = $this->createUser('author@example.com', false);
        $target = $this->createAnnouncement('The needle', 'Content.', new \DateTimeImmutable('-2 days'), $author);
        $this->createAnnouncement('Other title', 'Content.', new \DateTimeImmutable('-1 day'), $author);
        $this->client->loginUser($this->createUser('admin@example.com', true));

        $crawler = $this->client->request('GET', '/admin/announcements', [
            'admin_announcement_search' => ['announcement' => $target->getId()],
        ]);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.announcement-row'));
        self::assertSelectorTextContains('.announcement-title', 'The needle');
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

    private function createAnnouncement(string $title, string $content, \DateTimeImmutable $publishedAt, User $author): Announcement
    {
        $announcement = new Announcement($title, $content, $publishedAt, $author);
        $this->em->persist($announcement);
        $this->em->flush();

        return $announcement;
    }
}
