<?php

namespace App\Tests\Controller;

use App\Entity\Map;
use App\Entity\MapPoint;
use App\Entity\MapRoute;
use App\Entity\MapRouteType;
use App\Tests\ResetDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\Icons\IconRendererInterface;

final class MapControllerTest extends WebTestCase
{
    use ResetDatabase;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        $this->em = $em;

        $this->resetDatabase($this->em);
    }

    public function testIndexShowsMapsNewestFirst(): void
    {
        $this->createMap('The old world');
        $this->em->flush();
        $this->createMap('The new world');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/maps');

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Maps');

        self::assertSame(
            ['The new world', 'The old world'],
            $crawler->filter('.map-link')->extract(['_text']),
        );
    }

    public function testIndexWithNoMapsShowsEmptyState(): void
    {
        $this->client->request('GET', '/maps');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.map-empty', 'No maps yet.');
    }

    public function testShowRendersTheDisplayDataAndTheFilters(): void
    {
        $map = $this->createMap('A known world');
        $map->setImageFilename('fixture.png');
        $map->setImageSize(400, 300);

        $this->createPoint($map, 'Alphen', 0.5, 0.5, ['bi:bank', 'bi:shield-fill']);
        $this->createPoint($map, 'Beckton', 100, 200);

        $type = $this->createRouteType($map, 'Trade road', '#aa3311');
        $this->createRoute($type, 'The old road', [[0, 0], [400, 300]], true);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/maps/'.$map->getId());

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('A known world');
        self::assertSelectorTextContains('h1', 'A known world');

        // The space is scaled to the Point range limits, so the 400x300
        // image spans 120x90 units (scale 0.3): the position (0.5, 0.5)
        // lands at lng 0.15, lat 89.85 ((300 - 0.5) * 0.3), and (100, 200)
        // at lng 30, lat 30.
        $markers = $this->decodeListValue($crawler, 'markers');
        self::assertCount(2, $markers);

        $alphen = $markers[0];
        self::assertIsArray($alphen);
        self::assertSame('Alphen', $alphen['title']);
        self::assertSame(['lat' => 89.85, 'lng' => 0.15], $alphen['position']);

        $infoWindow = $alphen['infoWindow'];
        self::assertIsArray($infoWindow);
        self::assertSame('Alphen', $infoWindow['headerContent']);

        $extraData = $alphen['extra'];
        self::assertIsArray($extraData);
        $pinIcon = $extraData['pinIcon'];
        self::assertIsArray($pinIcon);
        self::assertIsString($pinIcon['html'] ?? null);

        /** @var IconRendererInterface $iconRenderer */
        $iconRenderer = static::getContainer()->get(IconRendererInterface::class);
        $bankIcon = $iconRenderer->renderIcon('bi:bank', ['xmlns' => 'http://www.w3.org/2000/svg', 'width' => '14', 'height' => '14']);
        self::assertStringContainsString($bankIcon, $pinIcon['html']);

        $beckton = $markers[1];
        self::assertIsArray($beckton);
        self::assertSame(['lat' => 30, 'lng' => 30], $beckton['position']);

        $polylines = $this->decodeListValue($crawler, 'polylines');
        self::assertCount(1, $polylines);

        $route = $polylines[0];
        self::assertIsArray($route);
        $routeExtra = $route['extra'];
        self::assertIsArray($routeExtra);
        self::assertSame('#aa3311', $routeExtra['color']);
        self::assertTrue($routeExtra['major']);

        $routeWindow = $route['infoWindow'];
        self::assertIsArray($routeWindow);
        self::assertSame('The old road', $routeWindow['headerContent']);

        $routePoints = $route['points'];
        self::assertIsArray($routePoints);
        self::assertSame(['lat' => 90, 'lng' => 0], $routePoints[0]);

        $extra = $this->decodeObjectValue($crawler, 'extra');
        $image = $extra['image'];
        self::assertIsArray($image);
        self::assertSame('/uploads/maps/fixture.png', $image['url']);

        $space = $extra['space'];
        self::assertIsArray($space);
        self::assertSame(120, $space['width']);
        self::assertSame(90, $space['height']);

        self::assertSame('0', $crawler->filter('[data-symfony--ux-leaflet-map--map-min-zoom-value]')->attr('data-symfony--ux-leaflet-map--map-min-zoom-value'));
        self::assertSame('4', $crawler->filter('[data-symfony--ux-leaflet-map--map-max-zoom-value]')->attr('data-symfony--ux-leaflet-map--map-max-zoom-value'));

        self::assertSelectorExists('#app-map-filter-pins');
        self::assertNotNull($crawler->filter('#app-map-filter-pins')->attr('checked'));
        self::assertSelectorExists('#app-map-filter-symbols');
        self::assertNotNull($crawler->filter('#app-map-filter-symbols')->attr('checked'));
        self::assertSelectorExists('#app-map-lines-all');
        self::assertNotNull($crawler->filter('#app-map-lines-all')->attr('checked'));
        self::assertSelectorExists('#app-map-lines-major');
        self::assertSelectorExists('#app-map-lines-none');
        self::assertSelectorExists('.app-map-legend-swatch[style*="#aa3311"]');
        self::assertSelectorTextContains('.app-map-legend', 'Trade road');
    }

    public function testShowEscapesTheAnnotationTexts(): void
    {
        $map = $this->createMap('A known world');
        $this->createPoint($map, '<b>City</b>', 0, 0);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/maps/'.$map->getId());

        self::assertResponseIsSuccessful();

        $markers = $this->decodeListValue($crawler, 'markers');
        $first = $markers[0];
        self::assertIsArray($first);

        $infoWindow = $first['infoWindow'] ?? null;
        self::assertIsArray($infoWindow);
        self::assertSame('&lt;b&gt;City&lt;/b&gt;', $infoWindow['headerContent']);
    }

    public function testShowWithoutSymbolsOmitsTheSymbolsFilter(): void
    {
        $map = $this->createMap('A plain world');
        $this->createPoint($map, 'Alphen', 10, 10);
        $this->em->flush();

        $this->client->request('GET', '/maps/'.$map->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#app-map-filter-pins');
        self::assertSelectorNotExists('#app-map-filter-symbols');
    }

    public function testShowWithoutAnnotationsOmitsTheFilterPanel(): void
    {
        $map = $this->createMap('An empty world');
        $this->em->flush();

        $this->client->request('GET', '/maps/'.$map->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#app-map-filter-pins');
        self::assertSelectorNotExists('#app-map-lines-all');
    }

    public function testShowWithoutImageRendersOverTheStoredSpace(): void
    {
        $map = $this->createMap('A world without a background');
        $map->setImageSize(200, 100);
        $this->createPoint($map, 'Alphen', 100, 50);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/maps/'.$map->getId());

        self::assertResponseIsSuccessful();

        $extra = $this->decodeObjectValue($crawler, 'extra');
        self::assertArrayNotHasKey('image', $extra);

        $space = $extra['space'];
        self::assertIsArray($space);
        self::assertSame(180, $space['width']);
        self::assertSame(90, $space['height']);
    }

    public function testShowUnknownMapReturns404(): void
    {
        $this->client->request('GET', '/maps/999999');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return list<mixed>
     */
    private function decodeListValue(\Symfony\Component\DomCrawler\Crawler $crawler, string $value): array
    {
        $decoded = json_decode($this->encodedValue($crawler, $value), true);

        if (!\is_array($decoded) || !\array_is_list($decoded)) {
            self::fail(\sprintf('The "%s" map value is not a JSON list.', $value));
        }

        return $decoded;
    }

    /**
     * @return array<mixed, mixed>
     */
    private function decodeObjectValue(\Symfony\Component\DomCrawler\Crawler $crawler, string $value): array
    {
        $decoded = json_decode($this->encodedValue($crawler, $value), true);

        if (!\is_array($decoded)) {
            self::fail(\sprintf('The "%s" map value is not a JSON object.', $value));
        }

        return $decoded;
    }

    private function encodedValue(\Symfony\Component\DomCrawler\Crawler $crawler, string $value): string
    {
        $attribute = \sprintf('data-symfony--ux-leaflet-map--map-%s-value', $value);

        $encoded = $crawler->filter(\sprintf('[%s]', $attribute))->attr($attribute);

        self::assertIsString($encoded);

        return $encoded;
    }

    private function createMap(string $name): Map
    {
        $map = new Map($name, new \DateTimeImmutable('now'));
        $this->em->persist($map);

        return $map;
    }

    /**
     * @param list<string> $symbols
     */
    private function createPoint(Map $map, string $title, float $x, float $y, array $symbols = []): MapPoint
    {
        $point = new MapPoint($map, $title, $x, $y);
        $point->setSymbols($symbols);
        $this->em->persist($point);

        return $point;
    }

    private function createRouteType(Map $map, string $name, string $color): MapRouteType
    {
        $type = new MapRouteType($map, $name, $color);
        $this->em->persist($type);

        return $type;
    }

    /**
     * @param list<list<float>> $points
     */
    private function createRoute(MapRouteType $type, ?string $name, array $points, bool $isMajor = false): MapRoute
    {
        $route = new MapRoute($type->getMap(), $type);
        $route->setName($name);
        $route->setIsMajor($isMajor);
        $route->setPoints(array_map(
            static fn (array $point): array => ['x' => $point[0], 'y' => $point[1]],
            $points,
        ));
        $this->em->persist($route);

        return $route;
    }
}
