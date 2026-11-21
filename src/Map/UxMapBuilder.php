<?php

namespace App\Map;

use App\Entity\Map as AppMap;
use App\Entity\MapPoint;
use App\Entity\MapRoute;
use Symfony\UX\Icons\IconRendererInterface;
use Symfony\UX\Map\Bridge\Leaflet\LeafletOptions;
use Symfony\UX\Map\InfoWindow;
use Symfony\UX\Map\Map;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;
use Symfony\UX\Map\Polyline;

/**
 * Turns one Map entity into the ux-map definition of the public display.
 *
 * The Leaflet map runs under L.CRS.Simple over an image plane, not a
 * geographic projection: the JavaScript controller sets the CRS and draws
 * the background through L.imageOverlay. The pixel space of the map is
 * scaled to the widest range Point accepts, because the UX Map Point class
 * validates latitudes against [-90, 90] and longitudes against [-180, 180]
 * even for image maps. The longest space side spans at most 180 units, so
 * the capped zoom range from the map parameters stays usable.
 */
final class UxMapBuilder
{
    private const MAX_LATITUDE = 90;
    private const MAX_LONGITUDE = 180;
    private const PIN_ICON = 'bi:geo-alt-fill';
    private const PIN_SIZE = [32, 32];
    private const PIN_ANCHOR = [16, 32];
    private const SYMBOL_SIZE = 14;

    public function __construct(
        private readonly IconRendererInterface $iconRenderer,
    ) {
    }

    public function build(AppMap $map): Map
    {
        $scale = min(
            self::MAX_LONGITUDE / $map->getSpaceWidth(),
            self::MAX_LATITUDE / $map->getSpaceHeight(),
        );
        $width = $map->getSpaceWidth() * $scale;
        $height = $map->getSpaceHeight() * $scale;

        $uxMap = new Map(
            options: (new LeafletOptions())
                ->tileLayer(false)
                ->attributionControl(false),
            center: new Point($height / 2, $width / 2),
            zoom: (float) $map->getMinZoom(),
            minZoom: (float) $map->getMinZoom(),
            maxZoom: (float) $map->getMaxZoom(),
        );

        foreach ($map->getPoints() as $point) {
            $uxMap->addMarker($this->createMarker($point, $map, $scale));
        }

        foreach ($map->getRoutes() as $route) {
            $uxMap->addPolyline($this->createPolyline($route, $map, $scale));
        }

        $extra = ['space' => ['width' => $width, 'height' => $height]];

        if (null !== $map->getImageFilename()) {
            // The leaflet controller inserts the popup content as HTML, so
            // the admin values are escaped before they reach the frontend.
            $extra['image'] = ['url' => '/uploads/maps/'.$map->getImageFilename()];
        }

        return $uxMap->extra($extra);
    }

    private function createMarker(MapPoint $point, AppMap $map, float $scale): Marker
    {
        $symbols = '';
        foreach ($point->getSymbols() as $symbol) {
            $symbols .= $this->renderIcon($symbol, self::SYMBOL_SIZE);
        }

        // The divIcon carries the pin and the symbols in one element, so the
        // symbols stay glued to the pin at every zoom level and the pins and
        // symbols filters can hide them through CSS classes.
        $html = '<span class="app-map-pin">'.$this->renderIcon(self::PIN_ICON, 24).'</span>'
            .('' !== $symbols ? '<span class="app-map-symbols">'.$symbols.'</span>' : '');

        return new Marker(
            position: $this->toPoint($point->getX(), $point->getY(), $map, $scale),
            title: $point->getTitle(),
            infoWindow: new InfoWindow(
                headerContent: htmlspecialchars($point->getTitle()),
                content: null !== $point->getDescription() ? nl2br(htmlspecialchars($point->getDescription())) : null,
            ),
            extra: ['pinIcon' => [
                'html' => $html,
                'size' => self::PIN_SIZE,
                'anchor' => self::PIN_ANCHOR,
            ]],
            id: 'point-'.($point->getId() ?? spl_object_id($point)),
        );
    }

    private function createPolyline(MapRoute $route, AppMap $map, float $scale): Polyline
    {
        $points = [];
        foreach ($route->getPoints() as $vertex) {
            $points[] = $this->toPoint($vertex['x'], $vertex['y'], $map, $scale);
        }

        return new Polyline(
            points: $points,
            infoWindow: new InfoWindow(
                headerContent: htmlspecialchars($route->getDisplayName()),
                content: null !== $route->getDescription() ? nl2br(htmlspecialchars($route->getDescription())) : null,
            ),
            extra: [
                'color' => $route->getType()->getColor(),
                'major' => $route->isMajor(),
            ],
            id: 'route-'.($route->getId() ?? spl_object_id($route)),
        );
    }

    /**
     * Leaflet places lat 0 at the bottom of a CRS.Simple map, while the map
     * coordinates grow downward from the top-left image corner.
     */
    private function toPoint(float $x, float $y, AppMap $map, float $scale): Point
    {
        return new Point(($map->getSpaceHeight() - $y) * $scale, $x * $scale);
    }

    private function renderIcon(string $name, int $size): string
    {
        return $this->iconRenderer->renderIcon($name, [
            'xmlns' => 'http://www.w3.org/2000/svg',
            'width' => (string) $size,
            'height' => (string) $size,
        ]);
    }
}
