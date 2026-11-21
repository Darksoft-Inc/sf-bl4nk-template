<?php

namespace App\Tests\Controller;

use App\Entity\Announcement;
use App\Entity\User;
use App\Tests\ResetDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AnnouncementControllerTest extends WebTestCase
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

    public function testIndexShowsAnnouncementsNewestFirst(): void
    {
        $author = $this->createUser('author@example.com');

        $this->createAnnouncement('First announcement', 'The oldest content.', new \DateTimeImmutable('-2 days'), $author);
        $this->createAnnouncement('Second announcement', 'The newest content.', new \DateTimeImmutable('-1 day'), $author);

        $crawler = $this->client->request('GET', '/announcements');

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Announcements');

        self::assertSame(
            ['Second announcement', 'First announcement'],
            $crawler->filter('.announcement-link')->extract(['_text']),
        );
    }

    public function testIndexWithNoAnnouncementsShowsEmptyState(): void
    {
        $this->client->request('GET', '/announcements');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.announcement-empty', 'No announcements yet.');
    }

    public function testShowDisplaysAnnouncement(): void
    {
        $author = $this->createUser('author@example.com');
        $announcement = $this->createAnnouncement('A title', "First line.\nSecond line.", new \DateTimeImmutable('2026-11-21 10:00'), $author);

        $this->client->request('GET', '/announcements/'.$announcement->getId());

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('A title');
        self::assertSelectorTextContains('h1', 'A title');
        self::assertSelectorTextContains('.announcement-content', 'First line.');
        self::assertSelectorTextContains('body', 'Posted by author@example.com');
    }

    public function testShowUnknownAnnouncementReturns404(): void
    {
        $this->client->request('GET', '/announcements/999999');

        self::assertResponseStatusCodeSame(404);
    }

    private function createUser(string $email): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setIsVerified(true)
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
