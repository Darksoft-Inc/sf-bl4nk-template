<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Map;
use App\Entity\MapPoint;
use App\Entity\MapRoute;
use App\Entity\MapRouteType;
use App\Entity\User;
use App\Tests\ResetDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * The map edit page links to the point, route type, and route management:
 * these tests cover the three entry controllers together, because they
 * share the map and the nominal coordinate space of a map without image.
 */
final class AdminMapEntryControllerTest extends WebTestCase
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
        $this->client->request('GET', '/admin/maps/1/points/new');

        self::assertResponseRedirects('/login');
    }

    public function testUsersWithoutAdminRoleAreDenied(): void
    {
        $this->client->loginUser($this->createUser('plain@example.com', false));

        $this->client->request('GET', '/admin/maps/1/points/new');

        self::assertResponseStatusCodeSame(403);
    }

    public function testPointNewCreatesAPointWithSymbols(): void
    {
        $map = $this->createMap('A known world');

        $this->client->loginUser($this->createUser('admin@example.com', true));

        $this->client->request('GET', '/admin/maps/'.$map->getId().'/points/new');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Save', [
            'map_point_form[title]' => 'Alphen',
            'map_point_form[description]' => 'The capital of the west.',
            'map_point_form[x]' => '120',
            'map_point_form[y]' => '80',
            'map_point_form[symbols]' => 'bi:bank bi:shield-fill',
        ]);

        self::assertResponseRedirects('/admin/maps/'.$map->getId().'/edit');

        $point = $this->em->createQuery('SELECT p FROM App\Entity\MapPoint p')->getSingleResult();
        \assert($point instanceof MapPoint);
        self::assertSame('Alphen', $point->getTitle());
        self::assertSame('The capital of the west.', $point->getDescription());
        self::assertSame(120.0, $point->getX());
        self::assertSame(80.0, $point->getY());
        self::assertSame(['bi:bank', 'bi:shield-fill'], $point->getSymbols());
    }

    public function testPointNewRejectsAnUnknownSymbol(): void
    {
        $map = $this->createMap('A known world');

        $this->client->loginUser($this->createUser('admin@example.com', true));

        $this->client->request('GET', '/admin/maps/'.$map->getId().'/points/new');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Save', [
            'map_point_form[title]' => 'Alphen',
            'map_point_form[x]' => '120',
            'map_point_form[y]' => '80',
            'map_point_form[symbols]' => 'bi:does-not-exist',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'The icon "bi:does-not-exist" does not exist in the icon sets.');

        self::assertSame(0, $this->em->createQuery('SELECT COUNT(p) FROM App\Entity\MapPoint p')->getSingleScalarResult());
    }

    public function testPointNewRejectsAPositionOutsideTheSpace(): void
    {
        $map = $this->createMap('A known world');

        $this->client->loginUser($this->createUser('admin@example.com', true));

        $this->client->request('GET', '/admin/maps/'.$map->getId().'/points/new');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Save', [
            'map_point_form[title]' => 'Alphen',
            'map_point_form[x]' => '600',
            'map_point_form[y]' => '80',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'This position lies outside the map space.');
    }

    public function testPointEditMovesThePinAndClearsTheSymbols(): void
    {
        $map = $this->createMap('A known world');
        $point = new MapPoint($map, 'Alphen', 120, 80);
        $point->setSymbols(['bi:bank']);
        $this->em->persist($point);
        $this->em->flush();

        $this->client->loginUser($this->createUser('admin@example.com', true));

        $crawler = $this->client->request('GET', '/admin/maps/points/'.$point->getId().'/edit');
        self::assertResponseIsSuccessful();
        self::assertNotNull($crawler->filter('#map_point_form_x')->attr('required'));
        self::assertNotNull($crawler->filter('#map_point_form_y')->attr('required'));

        // The admin enters Cartesian pixels, not geo coordinates, and the
        // help names the bounds of the map space.
        self::assertSelectorTextContains('#map_point_form_x_help', 'Pixels from the left edge of the map, 0 to 512.');
        self::assertSelectorTextContains('#map_point_form_y_help', 'Pixels from the top edge of the map, 0 to 512.');

        $this->client->submitForm('Save', [
            'map_point_form[title]' => 'Alphen moved',
            'map_point_form[x]' => '90',
            'map_point_form[y]' => '60',
            'map_point_form[symbols]' => '',
        ]);

        self::assertResponseRedirects('/admin/maps/'.$map->getId().'/edit');

        // The identity map of this test's manager holds the instance from
        // before the submit; clear it to read the stored row instead.
        $this->em->clear();

        $updated = $this->em->find(MapPoint::class, $point->getId());
        \assert($updated instanceof MapPoint);
        self::assertSame('Alphen moved', $updated->getTitle());
        self::assertSame(90.0, $updated->getX());
        self::assertSame(60.0, $updated->getY());
        self::assertSame([], $updated->getSymbols());
    }

    public function testPointEditKeepsThePositionWhenItIsSubmittedEmpty(): void
    {
        // A crafted post can submit the required position fields empty.
        // The empty_data option keeps the current position instead of
        // writing null into the non-nullable setters.
        $map = $this->createMap('A known world');
        $point = new MapPoint($map, 'Alphen', 120, 80);
        $this->em->persist($point);
        $this->em->flush();

        $this->client->loginUser($this->createUser('admin@example.com', true));

        $crawler = $this->client->request('GET', '/admin/maps/points/'.$point->getId().'/edit');
        $token = $crawler->filter('#map_point_form__token')->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', '/admin/maps/points/'.$point->getId().'/edit', [
            'map_point_form' => ['_token' => $token, 'title' => 'Alphen', 'description' => '', 'x' => '', 'y' => '', 'symbols' => ''],
        ]);

        self::assertResponseRedirects('/admin/maps/'.$map->getId().'/edit');

        $this->em->clear();

        $updated = $this->em->find(MapPoint::class, $point->getId());
        \assert($updated instanceof MapPoint);
        self::assertSame(120.0, $updated->getX());
        self::assertSame(80.0, $updated->getY());
    }

    public function testPointDeleteRemovesThePoint(): void
    {
        $map = $this->createMap('A known world');
        $point = new MapPoint($map, 'Alphen', 120, 80);
        $this->em->persist($point);
        $this->em->flush();

        $this->client->loginUser($this->createUser('admin@example.com', true));

        $crawler = $this->client->request('GET', '/admin/maps/'.$map->getId().'/edit');
        $token = $crawler->filter('.point-row input[name="_csrf_token"]')->first()->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', '/admin/maps/points/'.$point->getId().'/delete', [
            '_csrf_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/maps/'.$map->getId().'/edit');
        self::assertSame(0, $this->em->createQuery('SELECT COUNT(p) FROM App\Entity\MapPoint p')->getSingleScalarResult());
    }

    public function testRouteTypeNewEditAndDelete(): void
    {
        $map = $this->createMap('A known world');

        $this->client->loginUser($this->createUser('admin@example.com', true));

        $this->client->request('GET', '/admin/maps/'.$map->getId().'/route-types/new');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Save', [
            'map_route_type_form[name]' => 'Trade road',
            'map_route_type_form[color]' => '#aa3311',
        ]);

        self::assertResponseRedirects('/admin/maps/'.$map->getId().'/edit');

        $type = $this->em->createQuery('SELECT t FROM App\Entity\MapRouteType t')->getSingleResult();
        \assert($type instanceof MapRouteType);
        self::assertSame('Trade road', $type->getName());
        self::assertSame('#aa3311', $type->getColor());

        $this->client->request('GET', '/admin/maps/route-types/'.$type->getId().'/edit');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Save', [
            'map_route_type_form[name]' => 'Old trade road',
            'map_route_type_form[color]' => '#11aa33',
        ]);

        self::assertResponseRedirects('/admin/maps/'.$map->getId().'/edit');

        // Each client request reboots the kernel and its entity manager, so
        // the identity map of this test's manager holds the instance from
        // before the submit. Clearing it makes the find read the database.
        $this->em->clear();

        $updated = $this->em->find(MapRouteType::class, $type->getId());
        \assert($updated instanceof MapRouteType);
        self::assertSame('Old trade road', $updated->getName());
        self::assertSame('#11aa33', $updated->getColor());

        $crawler = $this->client->request('GET', '/admin/maps/'.$map->getId().'/edit');
        $token = $crawler->filter('.route-type-row input[name="_csrf_token"]')->first()->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', '/admin/maps/route-types/'.$type->getId().'/delete', [
            '_csrf_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/maps/'.$map->getId().'/edit');
        self::assertSame(0, $this->em->createQuery('SELECT COUNT(t) FROM App\Entity\MapRouteType t')->getSingleScalarResult());
    }

    public function testRouteTypeEditKeepsTheColorWhenItIsSubmittedEmpty(): void
    {
        // A crafted post can submit the color empty. The empty_data option
        // keeps the current color instead of writing null into the setter.
        $map = $this->createMap('A known world');
        $type = new MapRouteType($map, 'Trade road', '#aa3311');
        $this->em->persist($type);
        $this->em->flush();

        $this->client->loginUser($this->createUser('admin@example.com', true));

        $crawler = $this->client->request('GET', '/admin/maps/route-types/'.$type->getId().'/edit');
        $token = $crawler->filter('#map_route_type_form__token')->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', '/admin/maps/route-types/'.$type->getId().'/edit', [
            'map_route_type_form' => ['_token' => $token, 'name' => 'Old trade road', 'color' => ''],
        ]);

        self::assertResponseRedirects('/admin/maps/'.$map->getId().'/edit');

        $this->em->clear();

        $updated = $this->em->find(MapRouteType::class, $type->getId());
        \assert($updated instanceof MapRouteType);
        self::assertSame('Old trade road', $updated->getName());
        self::assertSame('#aa3311', $updated->getColor());
    }

    public function testRouteTypeDeleteRemovesItsRoutes(): void
    {
        $map = $this->createMap('A known world');
        $type = new MapRouteType($map, 'Trade road', '#aa3311');
        $this->em->persist($type);
        $route = new MapRoute($map, $type);
        $route->setPoints([['x' => 0, 'y' => 0], ['x' => 100, 'y' => 100]]);
        $this->em->persist($route);
        $this->em->flush();

        $this->client->loginUser($this->createUser('admin@example.com', true));

        $crawler = $this->client->request('GET', '/admin/maps/'.$map->getId().'/edit');
        $token = $crawler->filter('.route-type-row input[name="_csrf_token"]')->first()->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', '/admin/maps/route-types/'.$type->getId().'/delete', [
            '_csrf_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/maps/'.$map->getId().'/edit');
        self::assertSame(0, $this->em->createQuery('SELECT COUNT(r) FROM App\Entity\MapRoute r')->getSingleScalarResult());
    }

    public function testRouteNewRequiresARouteType(): void
    {
        $map = $this->createMap('A known world');

        $this->client->loginUser($this->createUser('admin@example.com', true));

        $this->client->request('GET', '/admin/maps/'.$map->getId().'/routes/new');

        self::assertResponseRedirects('/admin/maps/'.$map->getId().'/edit');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Create a route type before adding a route.');
    }

    public function testRouteNewCreatesAMajorRoute(): void
    {
        $map = $this->createMap('A known world');
        $type = new MapRouteType($map, 'Trade road', '#aa3311');
        $this->em->persist($type);
        $this->em->flush();

        $this->client->loginUser($this->createUser('admin@example.com', true));

        $this->client->request('GET', '/admin/maps/'.$map->getId().'/routes/new');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Save', [
            'map_route_form[type]' => $type->getId(),
            'map_route_form[name]' => 'The old road',
            'map_route_form[description]' => 'Walked by every caravan.',
            'map_route_form[isMajor]' => '1',
            'map_route_form[points]' => "120, 80\n200, 90\n300, 100",
        ]);

        self::assertResponseRedirects('/admin/maps/'.$map->getId().'/edit');

        $route = $this->em->createQuery('SELECT r FROM App\Entity\MapRoute r')->getSingleResult();
        \assert($route instanceof MapRoute);
        self::assertSame('The old road', $route->getName());
        self::assertSame($type->getId(), $route->getType()->getId());
        self::assertTrue($route->isMajor());
        self::assertSame(120.0, $route->getPoints()[0]['x']);
        self::assertSame(80.0, $route->getPoints()[0]['y']);
        self::assertSame(300.0, $route->getPoints()[2]['x']);
    }

    public function testRouteNewRejectsASinglePoint(): void
    {
        $map = $this->createMap('A known world');
        $type = new MapRouteType($map, 'Trade road', '#aa3311');
        $this->em->persist($type);
        $this->em->flush();

        $this->client->loginUser($this->createUser('admin@example.com', true));

        $this->client->request('GET', '/admin/maps/'.$map->getId().'/routes/new');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Save', [
            'map_route_form[type]' => $type->getId(),
            'map_route_form[points]' => '120, 80',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'A route needs at least two points, one per line.');

        self::assertSame(0, $this->em->createQuery('SELECT COUNT(r) FROM App\Entity\MapRoute r')->getSingleScalarResult());
    }

    public function testRouteNewRejectsAMalformedLineAndAPointOutsideTheSpace(): void
    {
        $map = $this->createMap('A known world');
        $type = new MapRouteType($map, 'Trade road', '#aa3311');
        $this->em->persist($type);
        $this->em->flush();

        $this->client->loginUser($this->createUser('admin@example.com', true));

        $this->client->request('GET', '/admin/maps/'.$map->getId().'/routes/new');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Save', [
            'map_route_form[type]' => $type->getId(),
            'map_route_form[points]' => "120, 80\nlater, tonight",
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'Line 2 is not a valid "x, y" pair.');

        $this->client->request('GET', '/admin/maps/'.$map->getId().'/routes/new');
        $this->client->submitForm('Save', [
            'map_route_form[type]' => $type->getId(),
            'map_route_form[points]' => "120, 80\n900, 900",
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'Line 2 lies outside the map space.');
    }

    public function testRouteEditChangesTheLine(): void
    {
        $map = $this->createMap('A known world');
        $type = new MapRouteType($map, 'Trade road', '#aa3311');
        $this->em->persist($type);
        $secondType = new MapRouteType($map, 'Sea lane', '#3366aa');
        $this->em->persist($secondType);
        $route = new MapRoute($map, $type);
        $route->setPoints([['x' => 0, 'y' => 0], ['x' => 100, 'y' => 100]]);
        $this->em->persist($route);
        $this->em->flush();

        $this->client->loginUser($this->createUser('admin@example.com', true));

        $this->client->request('GET', '/admin/maps/routes/'.$route->getId().'/edit');
        self::assertResponseIsSuccessful();

        // The edit form pre-fills the points from the stored line. The
        // crawler normalizes whitespace by default, which would erase the
        // one pair per line layout the textarea carries.
        $pointsTextarea = $this->client->getCrawler()->filter('textarea[name="map_route_form[points]"]')->text(null, false);
        self::assertSame("0, 0\n100, 100", trim($pointsTextarea));

        // The vertices are Cartesian pixels, and the help names the bounds.
        self::assertSelectorTextContains('#map_route_form_points_help', 'x from 0 to 512 and y from 0 to 512.');

        $this->client->submitForm('Save', [
            'map_route_form[type]' => $secondType->getId(),
            'map_route_form[name]' => 'The new road',
            'map_route_form[isMajor]' => '1',
            'map_route_form[points]' => "10, 10\n20, 20\n30, 30",
        ]);

        self::assertResponseRedirects('/admin/maps/'.$map->getId().'/edit');

        // The identity map of this test's manager holds the instance from
        // before the submit; clear it to read the stored row instead.
        $this->em->clear();

        $updated = $this->em->find(MapRoute::class, $route->getId());
        \assert($updated instanceof MapRoute);
        self::assertSame('The new road', $updated->getName());
        self::assertSame($secondType->getId(), $updated->getType()->getId());
        self::assertTrue($updated->isMajor());
        self::assertCount(3, $updated->getPoints());
    }

    public function testRouteEditKeepsTheTypeWhenTheSelectIsSubmittedEmpty(): void
    {
        // A crafted post can submit the type select empty. The empty_data
        // option keeps the current type instead of writing null into the
        // setter.
        $map = $this->createMap('A known world');
        $type = new MapRouteType($map, 'Trade road', '#aa3311');
        $this->em->persist($type);
        $route = new MapRoute($map, $type);
        $route->setPoints([['x' => 0, 'y' => 0], ['x' => 100, 'y' => 100]]);
        $this->em->persist($route);
        $this->em->flush();

        $this->client->loginUser($this->createUser('admin@example.com', true));

        $crawler = $this->client->request('GET', '/admin/maps/routes/'.$route->getId().'/edit');
        $token = $crawler->filter('#map_route_form__token')->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', '/admin/maps/routes/'.$route->getId().'/edit', [
            'map_route_form' => ['_token' => $token, 'type' => '', 'name' => 'The old road', 'points' => "0, 0\n100, 100"],
        ]);

        self::assertResponseRedirects('/admin/maps/'.$map->getId().'/edit');

        $this->em->clear();

        $updated = $this->em->find(MapRoute::class, $route->getId());
        \assert($updated instanceof MapRoute);
        self::assertSame($type->getId(), $updated->getType()->getId());
    }

    public function testRouteEditFallsBackToTheTypeNameWhenTheNameIsCleared(): void
    {
        // The name field is optional, so the browser sends it empty. An
        // empty name means no name, and the popup falls back to the type.
        $map = $this->createMap('A known world');
        $type = new MapRouteType($map, 'Trade road', '#aa3311');
        $this->em->persist($type);
        $route = new MapRoute($map, $type);
        $route->setName('The old road');
        $route->setPoints([['x' => 0, 'y' => 0], ['x' => 100, 'y' => 100]]);
        $this->em->persist($route);
        $this->em->flush();

        $this->client->loginUser($this->createUser('admin@example.com', true));

        $this->client->request('GET', '/admin/maps/routes/'.$route->getId().'/edit');
        $this->client->submitForm('Save', [
            'map_route_form[type]' => $type->getId(),
            'map_route_form[name]' => '',
            'map_route_form[points]' => "0, 0\n100, 100",
        ]);

        self::assertResponseRedirects('/admin/maps/'.$map->getId().'/edit');

        $this->em->clear();

        $updated = $this->em->find(MapRoute::class, $route->getId());
        \assert($updated instanceof MapRoute);
        self::assertNull($updated->getName());
        self::assertSame('Trade road', $updated->getDisplayName());
    }

    public function testRouteDeleteRemovesTheRoute(): void
    {
        $map = $this->createMap('A known world');
        $type = new MapRouteType($map, 'Trade road', '#aa3311');
        $this->em->persist($type);
        $route = new MapRoute($map, $type);
        $route->setPoints([['x' => 0, 'y' => 0], ['x' => 100, 'y' => 100]]);
        $this->em->persist($route);
        $this->em->flush();

        $this->client->loginUser($this->createUser('admin@example.com', true));

        $crawler = $this->client->request('GET', '/admin/maps/'.$map->getId().'/edit');
        $token = $crawler->filter('.route-row input[name="_csrf_token"]')->first()->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', '/admin/maps/routes/'.$route->getId().'/delete', [
            '_csrf_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/maps/'.$map->getId().'/edit');
        self::assertSame(0, $this->em->createQuery('SELECT COUNT(r) FROM App\Entity\MapRoute r')->getSingleScalarResult());
    }

    public function testMapDeleteCascadesToEveryEntry(): void
    {
        $map = $this->createMap('A doomed world');
        $type = new MapRouteType($map, 'Trade road', '#aa3311');
        $this->em->persist($type);
        $route = new MapRoute($map, $type);
        $route->setPoints([['x' => 0, 'y' => 0], ['x' => 100, 'y' => 100]]);
        $this->em->persist($route);
        $this->em->persist(new MapPoint($map, 'Alphen', 120, 80));
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
        self::assertSame(0, $this->em->createQuery('SELECT COUNT(p) FROM App\Entity\MapPoint p')->getSingleScalarResult());
        self::assertSame(0, $this->em->createQuery('SELECT COUNT(r) FROM App\Entity\MapRoute r')->getSingleScalarResult());
        self::assertSame(0, $this->em->createQuery('SELECT COUNT(t) FROM App\Entity\MapRouteType t')->getSingleScalarResult());
    }

    private function createMap(string $name): Map
    {
        $map = new Map($name, new \DateTimeImmutable('now'));
        $this->em->persist($map);
        $this->em->flush();

        return $map;
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
