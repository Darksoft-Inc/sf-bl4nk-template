<?php

namespace App\Tests\Controller;

use App\Entity\Announcement;
use App\Tests\ResetDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AnnouncementControllerTest extends WebTestCase
{
    use ResetDatabase;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine')->getManager();
        $this->em = $em;

        $this->resetDatabase($this->em);
    }

    public function testIndexShowsAnnouncementsNewestFirst(): void
    {
        $this->createAnnouncement('First announcement', 'The oldest content.', new \DateTimeImmutable('-2 days'));
        $this->createAnnouncement('Second announcement', 'The newest content.', new \DateTimeImmutable('-1 day'));

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
        $announcement = $this->createAnnouncement('A title', "First line.\nSecond line.", new \DateTimeImmutable('2026-11-21 10:00'));

        $this->client->request('GET', '/announcements/'.$announcement->getId());

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('A title');
        self::assertSelectorTextContains('h1', 'A title');
        self::assertSelectorTextContains('.announcement-content', 'First line.');
    }

    public function testShowUnknownAnnouncementReturns404(): void
    {
        $this->client->request('GET', '/announcements/999999');

        self::assertResponseStatusCodeSame(404);
    }

    private function createAnnouncement(string $title, string $content, \DateTimeImmutable $publishedAt): Announcement
    {
        $announcement = new Announcement($title, $content, $publishedAt);
        $this->em->persist($announcement);
        $this->em->flush();

        return $announcement;
    }
}
