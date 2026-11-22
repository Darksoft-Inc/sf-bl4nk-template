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
        $announcement = $this->createAnnouncement('A title', "First **line**.\n\n- an item", new \DateTimeImmutable('2026-11-21 10:00'), $author);

        $this->client->request('GET', '/announcements/'.$announcement->getId());

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('A title');
        self::assertSelectorTextContains('h1', 'A title');
        self::assertSelectorTextContains('.announcement-content', 'First line.');
        self::assertSelectorTextContains('.announcement-content strong', 'line');
        self::assertSelectorTextContains('.announcement-content li', 'an item');
        self::assertSelectorTextContains('body', 'Posted by author@example.com');
    }

    public function testShowEscapesRawHtmlAndDropsUnsafeLinks(): void
    {
        $author = $this->createUser('author@example.com');
        $announcement = $this->createAnnouncement(
            'A dangerous title',
            '<script>alert("xss")</script>'."\n\n".'A [link](javascript:alert(1)) in the content.',
            new \DateTimeImmutable('2026-11-21 10:00'),
            $author,
        );

        $crawler = $this->client->request('GET', '/announcements/'.$announcement->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.announcement-content', 'alert("xss")');
        self::assertSelectorTextContains('.announcement-content', 'A link in the content.');

        $contentHtml = $crawler->filter('.announcement-content')->html();
        self::assertStringContainsString('&lt;script&gt;', $contentHtml);
        self::assertStringNotContainsStringIgnoringCase('<script', $contentHtml);
        self::assertStringNotContainsStringIgnoringCase('javascript:', $contentHtml);
        self::assertCount(0, $crawler->filter('.announcement-content a[href]'));
    }

    public function testShowUnknownAnnouncementReturns404(): void
    {
        $this->client->request('GET', '/announcements/999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testAutocompleteEndpointReturnsMatchingAnnouncements(): void
    {
        $author = $this->createUser('author@example.com');
        $match = $this->createAnnouncement('The galaxy opens', 'Welcome to the galaxy.', new \DateTimeImmutable('-2 days'), $author);
        $this->createAnnouncement('Maintenance window', 'The servers restart tonight.', new \DateTimeImmutable('-1 day'), $author);

        $this->client->request('GET', '/autocomplete/announcement_autocomplete_field', ['query' => 'galaxy']);

        self::assertResponseIsSuccessful();
        self::assertResponseFormatSame('json');

        /** @var array{results: list<array{value: string, text: string}>} $data */
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        self::assertCount(1, $data['results']);
        self::assertSame('The galaxy opens', $data['results'][0]['text']);
        self::assertSame((string) $match->getId(), $data['results'][0]['value']);
    }

    public function testSearchFormRedirectsToTheSelectedAnnouncement(): void
    {
        $author = $this->createUser('author@example.com');
        $announcement = $this->createAnnouncement('A title', 'Some content.', new \DateTimeImmutable('-1 day'), $author);

        $this->client->request('GET', '/announcements', [
            'announcement_search' => ['announcement' => $announcement->getId()],
        ]);

        self::assertResponseRedirects('/announcements/'.$announcement->getId());
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
