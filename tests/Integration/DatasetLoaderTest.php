<?php

namespace App\Tests\Integration;

use App\Dataset\DatasetException;
use App\Dataset\DatasetLoader;
use App\Dataset\DatasetMode;
use App\Dataset\DatasetRecord;
use App\Dataset\DatasetResolver;
use App\Dataset\DatasetTypeInterface;
use App\Entity\Announcement;
use App\Entity\Map;
use App\Entity\MapPoint;
use App\Entity\MapRoute;
use App\Entity\MapRouteType;
use App\Entity\User;
use App\Tests\ResetDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Runs the dataset loader against the test database. The fixture files
 * in tests/fixtures/datasets hold the data: one full content set, one
 * partial update, one removal, and one file per error.
 */
final class DatasetLoaderTest extends KernelTestCase
{
    use ResetDatabase;

    private const FIXTURES = __DIR__.'/../fixtures/datasets';

    private EntityManagerInterface $em;
    private DatasetLoader $loader;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        $this->em = $em;

        /** @var DatasetLoader $loader */
        $loader = $container->get(DatasetLoader::class);
        $this->loader = $loader;

        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $this->passwordHasher = $passwordHasher;

        $this->resetDatabase($this->em);
    }

    public function testLoadInsertsRecordsAndResolvesReferences(): void
    {
        $result = $this->loader->load([$this->fixture('content.yaml')]);

        $created = array_map(static fn (array $counts): int => $counts['created'], $result->counts());
        ksort($created);
        self::assertSame(
            ['announcements' => 1, 'map_points' => 1, 'map_route_types' => 1, 'map_routes' => 1, 'maps' => 1, 'users' => 1],
            $created,
        );
        self::assertSame(1, $result->fileCount());

        $this->em->clear();

        $jane = $this->one(User::class);
        self::assertSame('jane@example.com', $jane->getEmail());
        self::assertSame('jane', $jane->getDatasetSlug());
        self::assertTrue($jane->isVerified());
        self::assertTrue($this->passwordHasher->isPasswordValid($jane, 'FixturePassword42!'), 'The plain password of the record is hashed at load time.');

        $announcement = $this->one(Announcement::class);
        self::assertSame('welcome', $announcement->getDatasetSlug());
        self::assertSame('Welcome to the demo', $announcement->getTitle());
        self::assertSame("First line.\nSecond line.\n", $announcement->getContent(), 'The "|" block scalar keeps its trailing newline.');
        self::assertSame('2026-11-01 10:00', $announcement->getPublishedAt()->format('Y-m-d H:i'));
        self::assertSame('jane@example.com', $announcement->getAuthor()->getEmail(), 'The author reference resolves to the loaded user.');

        $map = $this->one(Map::class);
        self::assertSame('old-town', $map->getDatasetSlug());
        self::assertSame('Old town', $map->getName());
        self::assertSame(1, $map->getMinZoom());
        self::assertSame(5, $map->getMaxZoom());
        self::assertNull($map->getImageFilename());

        $point = $this->one(MapPoint::class);
        self::assertSame('market', $point->getDatasetSlug());
        self::assertSame($map->getId(), $point->getMap()->getId());
        self::assertSame(120.0, $point->getX());
        self::assertSame(80.0, $point->getY());
        self::assertSame(['bi:geo-alt-fill'], $point->getSymbols());

        $routeType = $this->one(MapRouteType::class);
        self::assertSame('main-roads', $routeType->getDatasetSlug());
        self::assertSame($map->getId(), $routeType->getMap()->getId());
        self::assertSame('#c9a13b', $routeType->getColor());

        $route = $this->one(MapRoute::class);
        self::assertSame('high-street', $route->getDatasetSlug());
        self::assertSame($routeType->getId(), $route->getType()->getId());
        self::assertTrue($route->isMajor());
        self::assertSame(
            [['x' => 10.0, 'y' => 20.0], ['x' => 200.0, 'y' => 180.0], ['x' => 400.0, 'y' => 120.0]],
            $route->getPoints(),
        );
    }

    public function testRerunUpdatesExistingRowsInsteadOfDuplicating(): void
    {
        $this->loader->load([$this->fixture('content.yaml')]);
        $result = $this->loader->load([$this->fixture('content.yaml')], mode: DatasetMode::Update);

        self::assertSame(1, $result->counts()['users']['updated']);
        self::assertSame(0, $result->counts()['users']['created']);
        self::assertCount(1, $this->em->getRepository(User::class)->findAll(), 'The second load updates the known slug instead of adding a row.');
    }

    public function testInsertModeRefusesSlugsThatAlreadyExist(): void
    {
        $this->loader->load([$this->fixture('content.yaml')]);

        try {
            $this->loader->load([$this->fixture('content.yaml')]);
            self::fail('The insert mode must refuse a slug that already exists.');
        } catch (DatasetException $e) {
            self::assertStringContainsString('Slugs that already exist', $e->getMessage());
            self::assertStringContainsString('jane', $e->getMessage());
            self::assertStringContainsString('welcome', $e->getMessage());
            self::assertStringContainsString('Run with --update', $e->getMessage());
            self::assertStringContainsString('--clear', $e->getMessage());
        }

        $this->em->clear();

        self::assertCount(1, $this->em->getRepository(User::class)->findAll(), 'The refused load leaves the database as it was.');
        self::assertCount(1, $this->em->getRepository(Announcement::class)->findAll());
    }

    public function testPartialRecordUpdatesOnlyTheNamedFields(): void
    {
        $this->loader->load([$this->fixture('content.yaml')]);
        $this->loader->load([$this->fixture('update.yaml')], mode: DatasetMode::Update);

        $this->em->clear();

        $jane = $this->one(User::class);
        self::assertFalse($jane->isVerified(), 'The named field changed.');
        self::assertTrue($this->passwordHasher->isPasswordValid($jane, 'NewFixturePassword42!'), 'Naming "password" resets it.');
        self::assertSame('jane@example.com', $jane->getEmail(), 'An unnamed field keeps its stored value.');
    }

    public function testRemovalTagDeletesTheSlugRowOnly(): void
    {
        $this->loader->load([$this->fixture('content.yaml')]);
        $result = $this->loader->load([$this->fixture('removal.yaml')], mode: DatasetMode::Update);

        self::assertSame(1, $result->counts()['map_route_types']['removed']);

        $this->em->clear();

        self::assertCount(0, $this->em->getRepository(MapRouteType::class)->findAll());
        self::assertCount(0, $this->em->getRepository(MapRoute::class)->findAll(), 'The routes of the removed type cascade away.');
        self::assertCount(1, $this->em->getRepository(Map::class)->findAll(), 'The map keeps its other entries.');
        self::assertCount(1, $this->em->getRepository(MapPoint::class)->findAll());
        self::assertCount(1, $this->em->getRepository(User::class)->findAll());

        $reload = $this->loader->load([$this->fixture('content.yaml')], mode: DatasetMode::Update);
        self::assertSame(1, $reload->counts()['map_route_types']['created'], 'After the removal, the same record can be loaded again.');
    }

    public function testARemovalIsRefusedWhenRowsOutsideTheDatasetWouldCascade(): void
    {
        $this->loader->load([$this->fixture('content.yaml')]);

        $map = $this->em->getRepository(Map::class)->findOneBy(['datasetSlug' => 'old-town']);
        self::assertNotNull($map);

        $this->em->persist(new MapPoint($map, 'Hand pin', 10.0, 10.0));
        $this->em->flush();

        try {
            $this->loader->load([$this->fixture('remove-map.yaml')], mode: DatasetMode::Update);
            self::fail('A removal that would delete rows outside the dataset must fail the load.');
        } catch (DatasetException $e) {
            self::assertStringContainsString('maps.old-town', $e->getMessage());
            self::assertStringContainsString('MapPoint', $e->getMessage());
            self::assertStringContainsString('1 row(s)', $e->getMessage());
            self::assertStringContainsString('a removal would delete them', $e->getMessage());
        }

        $this->em->clear();

        self::assertCount(2, $this->em->getRepository(MapPoint::class)->findAll(), 'The hand pin survives the refused removal.');
        self::assertCount(1, $this->em->getRepository(Map::class)->findAll(), 'The refused removal leaves the map in place.');
    }

    public function testARemovalIsRefusedWhenRowsWouldBlockTheDelete(): void
    {
        $this->loader->load([$this->fixture('content.yaml')]);

        try {
            $this->loader->load([$this->fixture('remove-user.yaml')], mode: DatasetMode::Update);
            self::fail('A removal that the database would refuse must fail with the guard message.');
        } catch (DatasetException $e) {
            self::assertStringContainsString('users.jane', $e->getMessage());
            self::assertStringContainsString('Announcement', $e->getMessage());
            self::assertStringContainsString('the database would refuse the removal', $e->getMessage());
        }

        $this->em->clear();

        self::assertCount(1, $this->em->getRepository(User::class)->findAll(), 'The refused removal leaves the user in place.');
        self::assertCount(1, $this->em->getRepository(Announcement::class)->findAll(), 'The announcement that referenced the user survives.');
    }

    public function testRemovalEntriesNeedTheUpdateMode(): void
    {
        $this->loader->load([$this->fixture('content.yaml')]);

        try {
            $this->loader->load([$this->fixture('removal.yaml')]);
            self::fail('A removal entry outside the update mode must fail the load.');
        } catch (DatasetException $e) {
            self::assertStringContainsString('map_route_types.main-roads', $e->getMessage());
            self::assertStringContainsString('removal entries need --update', $e->getMessage());
        }

        $this->em->clear();

        self::assertCount(1, $this->em->getRepository(MapRouteType::class)->findAll(), 'The refused load leaves the removal target in place.');
    }

    public function testClearEmptiesEveryTableBeforeLoading(): void
    {
        $handUser = (new User())->setEmail('hand@example.com')->setIsVerified(true);
        $handUser->setPassword($this->passwordHasher->hashPassword($handUser, 'hand-password'));
        $this->em->persist($handUser);
        $this->em->persist(new Announcement('Hand written', 'Made through the app.', new \DateTimeImmutable('2026-11-20 10:00'), $handUser));
        $this->em->flush();

        $result = $this->loader->load([$this->fixture('content.yaml')], mode: DatasetMode::Clear);

        self::assertSame(1, $result->counts()['users']['created']);
        self::assertGreaterThan(0, $result->cleared());

        $this->em->clear();

        self::assertCount(1, $this->em->getRepository(User::class)->findAll(), 'The clear emptied the tables, so only the loaded user remains.');
        self::assertCount(1, $this->em->getRepository(Announcement::class)->findAll(), 'The hand-made announcement is gone with the clear.');
        self::assertCount(0, $this->em->getRepository(User::class)->findBy(['email' => 'hand@example.com']));
    }

    public function testDryRunWritesNothing(): void
    {
        $result = $this->loader->load([$this->fixture('content.yaml')], dryRun: true);

        self::assertSame(1, $result->counts()['users']['created'], 'The dry run reports the records it would write.');

        $this->em->clear();

        self::assertCount(0, $this->em->getRepository(User::class)->findAll());
        self::assertCount(0, $this->em->getRepository(Announcement::class)->findAll());
    }

    public function testUnknownReferenceFailsBeforeAnyWrite(): void
    {
        $message = $this->loadFailure('errors/unknown-reference.yaml');

        self::assertStringContainsString('unknown-reference.yaml] announcements.orphan', $message);
        self::assertStringContainsString('unknown reference "@users.nobody"', $message);
        self::assertCount(0, $this->em->getRepository(User::class)->findAll(), 'A reference failure leaves the database untouched.');
    }

    public function testUnknownSectionFails(): void
    {
        $message = $this->loadFailure('errors/unknown-section.yaml');

        self::assertStringContainsString('unknown type "user"', $message);
        self::assertStringContainsString('Known types:', $message);
        foreach (['users', 'announcements', 'maps', 'map_points', 'map_route_types', 'map_routes'] as $type) {
            self::assertStringContainsString($type, $message, 'The error names every known type.');
        }
    }

    public function testDuplicateSlugFails(): void
    {
        $message = $this->loadFailure(['content.yaml', 'errors/duplicate.yaml']);

        self::assertStringContainsString('users.jane: the slug is already defined', $message);
        self::assertStringContainsString('content.yaml', $message);
        self::assertStringContainsString('duplicate.yaml', $message);
    }

    public function testMissingRequiredFieldFails(): void
    {
        $message = $this->loadFailure('errors/missing-field.yaml');

        self::assertStringContainsString('missing-field.yaml] users.shorty', $message);
        self::assertStringContainsString('the field "password" is required when the slug is new', $message);
    }

    public function testUnknownFieldFails(): void
    {
        $message = $this->loadFailure('errors/unknown-field.yaml');

        self::assertStringContainsString('unknown field "pasword"', $message);
        self::assertStringContainsString('Known fields: email, password, roles, isVerified', $message);
    }

    public function testUnrenderableSymbolFails(): void
    {
        $message = $this->loadFailure('errors/bad-symbol.yaml');

        self::assertStringContainsString('map_points.market', $message);
        self::assertStringContainsString('the symbol "not-an-icon-xyz" is not a renderable icon', $message);
    }

    public function testSingleVertexRouteFails(): void
    {
        $message = $this->loadFailure('errors/bad-points.yaml');

        self::assertStringContainsString('map_routes.stub', $message);
        self::assertStringContainsString('"points" needs at least two vertices', $message);
    }

    public function testPositionOutsideTheMapSpaceFails(): void
    {
        $message = $this->loadFailure('errors/out-of-space.yaml');

        self::assertStringContainsString('map_points.far', $message);
        self::assertStringContainsString('is outside the 512 x 512 pixel space of the map', $message);
    }

    public function testRemovalEntryWithExtraFieldsFails(): void
    {
        $message = $this->loadFailure('errors/removal-with-fields.yaml');

        self::assertStringContainsString('users.jane', $message);
        self::assertStringContainsString('a removal entry carries only the "remove" flag', $message);
    }

    public function testReferenceToARemovedRecordFails(): void
    {
        $message = $this->loadFailure('errors/reference-to-removed.yaml', mode: DatasetMode::Update);

        self::assertStringContainsString('map_routes.r', $message);
        self::assertStringContainsString('"@map_route_types.gone" points at a record marked for removal', $message);
    }

    public function testCircularReferenceFails(): void
    {
        $fakeType = static fn (string $name): DatasetTypeInterface => new class($name) implements DatasetTypeInterface {
            public function __construct(
                private readonly string $name,
            ) {
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function getEntityClass(): string
            {
                return \stdClass::class;
            }

            public function getFieldNames(): array
            {
                return ['other'];
            }

            public function getRequiredFieldNames(): array
            {
                return ['other'];
            }

            public function create(DatasetRecord $record, DatasetResolver $resolver): object
            {
                return new \stdClass();
            }

            public function update(object $entity, DatasetRecord $record, DatasetResolver $resolver): void
            {
            }
        };

        $loader = new DatasetLoader($this->em, [$fakeType('left'), $fakeType('right')]);

        try {
            $loader->load([$this->fixture('errors/circular.yaml')]);
            self::fail('A circular reference must fail the load.');
        } catch (DatasetException $e) {
            self::assertStringContainsString('Circular references among: left.one, right.two', $e->getMessage());
        }
    }

    public function testADatabaseRejectionRollsBackTheWholeLoad(): void
    {
        $hand = (new User())->setEmail('jane@example.com')->setIsVerified(true);
        $hand->setPassword($this->passwordHasher->hashPassword($hand, 'hand-password'));
        $this->em->persist($hand);
        $this->em->flush();

        try {
            $this->loader->load([$this->fixture('content.yaml')]);
            self::fail('A record colliding with an existing unique value must fail the load.');
        } catch (DatasetException $e) {
            self::assertStringContainsString('The database rejected the dataset', $e->getMessage());
        }

        $this->em->clear();

        self::assertCount(1, $this->em->getRepository(User::class)->findAll(), 'The rejected load leaves the database as it was.');
        self::assertCount(0, $this->em->getRepository(Announcement::class)->findAll());
        self::assertCount(0, $this->em->getRepository(Map::class)->findAll());
    }

    public function testPartialLoadResolvesReferencesAgainstTheDatabase(): void
    {
        $this->loader->load([$this->fixture('content.yaml')]);
        $result = $this->loader->load([$this->fixture('partial.yaml')]);

        self::assertSame(1, $result->counts()['map_points']['created']);
        self::assertSame(1, $result->counts()['announcements']['created']);

        $this->em->clear();

        $map = $this->em->getRepository(Map::class)->findOneBy(['datasetSlug' => 'old-town']);
        self::assertNotNull($map);

        $harbor = $this->em->getRepository(MapPoint::class)->findOneBy(['datasetSlug' => 'harbor']);
        self::assertNotNull($harbor);
        self::assertSame($map->getId(), $harbor->getMap()->getId(), 'A reference to a row of an earlier load resolves against the database.');

        $announcement = $this->em->getRepository(Announcement::class)->findOneBy(['datasetSlug' => 'followup']);
        self::assertNotNull($announcement);
        self::assertSame('jane@example.com', $announcement->getAuthor()->getEmail());

        self::assertCount(1, $this->em->getRepository(User::class)->findAll(), 'The partial load adds no row of its referenced types.');
    }

    public function testClearLoadMustBeSelfContained(): void
    {
        $this->loader->load([$this->fixture('content.yaml')]);

        try {
            $this->loader->load([$this->fixture('partial.yaml')], mode: DatasetMode::Clear);
            self::fail('A clear load cannot reference rows it empties first.');
        } catch (DatasetException $e) {
            self::assertStringContainsString('partial.yaml] map_points.harbor', $e->getMessage());
            self::assertStringContainsString('"@maps.old-town" targets a row of an earlier load, but --clear empties every table first', $e->getMessage());
        }

        $this->em->clear();

        self::assertCount(1, $this->em->getRepository(User::class)->findAll(), 'The refused clear leaves every row in place.');
        self::assertCount(1, $this->em->getRepository(Map::class)->findAll());
    }

    public function testBatchedWritesResolveReferencesAcrossBatches(): void
    {
        $result = $this->loader->load([$this->fixture('content.yaml')], batchSize: 1);

        self::assertSame(6, array_sum(array_map(static fn (array $counts): int => $counts['created'], $result->counts())));
        self::assertSame(0, $this->em->getUnitOfWork()->size(), 'A batch size of 1 flushes and clears after every record, so nothing stays managed.');

        $this->em->clear();

        $announcement = $this->one(Announcement::class);
        self::assertSame('jane@example.com', $announcement->getAuthor()->getEmail(), 'A reference written in an earlier batch resolves through the row id.');

        $point = $this->one(MapPoint::class);
        self::assertSame(120.0, $point->getX());
        self::assertSame($this->one(Map::class)->getId(), $point->getMap()->getId());

        $route = $this->one(MapRoute::class);
        self::assertSame($this->one(MapRouteType::class)->getId(), $route->getType()->getId());
    }

    public function testMissingPathFails(): void
    {
        try {
            $this->loader->load(['/nowhere/datasets']);
            self::fail('A missing path must fail the load.');
        } catch (DatasetException $e) {
            self::assertStringContainsString('The dataset path "/nowhere/datasets" does not exist.', $e->getMessage());
        }
    }

    /**
     * @param list<string>|string $fixtures
     */
    private function loadFailure(array|string $fixtures, DatasetMode $mode = DatasetMode::Insert): string
    {
        $paths = \is_array($fixtures) ? $fixtures : [$fixtures];

        try {
            $this->loader->load(array_map($this->fixture(...), $paths), mode: $mode);
            self::fail('The load must fail.');
        } catch (DatasetException $e) {
            return $e->getMessage();
        }
    }

    private function fixture(string $name): string
    {
        return self::FIXTURES.'/'.$name;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function one(string $class): object
    {
        $entity = $this->em->getRepository($class)->findOneBy([]);
        self::assertNotNull($entity, \sprintf('One %s row exists.', $class));

        return $entity;
    }
}
