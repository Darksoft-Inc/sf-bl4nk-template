<?php

namespace App\Dataset\Type;

use App\Dataset\DatasetRecord;
use App\Dataset\DatasetResolver;
use App\Dataset\DatasetTypeInterface;
use App\Entity\Map;
use App\Entity\MapRoute;
use App\Entity\MapRouteType;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * The "map_routes" section. The vertices are a list of "x, y" pairs, each
 * a two-number list, in the pixel space of the referenced map. The same
 * bounds and two-vertex minimum hold as in the admin form.
 */
#[AutoconfigureTag('app.dataset_type')]
final class MapRouteDatasetType implements DatasetTypeInterface
{
    private const FIELDS = ['map', 'type', 'name', 'description', 'isMajor', 'points'];

    public function getName(): string
    {
        return 'map_routes';
    }

    public function getEntityClass(): string
    {
        return MapRoute::class;
    }

    public function getFieldNames(): array
    {
        return self::FIELDS;
    }

    public function getRequiredFieldNames(): array
    {
        return ['map', 'type', 'points'];
    }

    public function create(DatasetRecord $record, DatasetResolver $resolver): object
    {
        $route = new MapRoute($this->map($record, $resolver), $this->routeType($record, $resolver));
        $route->setDatasetSlug($record->slug);

        $this->apply($route, $record, $resolver);

        return $route;
    }

    public function update(object $entity, DatasetRecord $record, DatasetResolver $resolver): void
    {
        \assert($entity instanceof MapRoute);

        $this->apply($entity, $record, $resolver);
    }

    private function apply(MapRoute $route, DatasetRecord $record, DatasetResolver $resolver): void
    {
        $map = $route->getMap();

        if ($record->has('map')) {
            $map = $this->map($record, $resolver);
            $route->setMap($map);
        }

        if ($record->has('type')) {
            $route->setType($this->routeType($record, $resolver));
        }

        if ($record->has('name')) {
            $route->setName($record->nullableString('name'));
        }

        if ($record->has('description')) {
            $route->setDescription($record->nullableString('description'));
        }

        if ($record->has('isMajor')) {
            $route->setIsMajor($record->boolean('isMajor'));
        }

        if ($record->has('points')) {
            $route->setPoints($this->vertices($record, $map));
        }
    }

    private function map(DatasetRecord $record, DatasetResolver $resolver): Map
    {
        $map = $record->entity('map', 'maps', $resolver);
        \assert($map instanceof Map);

        return $map;
    }

    private function routeType(DatasetRecord $record, DatasetResolver $resolver): MapRouteType
    {
        $type = $record->entity('type', 'map_route_types', $resolver);
        \assert($type instanceof MapRouteType);

        return $type;
    }

    /**
     * @return list<array{x: float, y: float}>
     */
    private function vertices(DatasetRecord $record, Map $map): array
    {
        $value = $record->value('points');

        if (!\is_array($value) || !array_is_list($value)) {
            $record->fail('"points" must be a list of "x, y" pairs');
        }

        $vertices = [];
        $index = 0;

        foreach ($value as $vertex) {
            ++$index;

            if (\is_array($vertex) && array_is_list($vertex) && 2 === \count($vertex)) {
                $x = $vertex[0];
                $y = $vertex[1];
            } elseif (\is_array($vertex) && \array_key_exists('x', $vertex) && \array_key_exists('y', $vertex)) {
                $x = $vertex['x'];
                $y = $vertex['y'];
            } else {
                $record->fail(\sprintf('"points[%d]" must be a pair like [10, 20] or {x: 10, y: 20}', $index));
            }

            if ((!\is_int($x) && !\is_float($x)) || (!\is_int($y) && !\is_float($y))) {
                $record->fail(\sprintf('"points[%d]" must hold two numbers', $index));
            }

            if ($x < 0 || $x > $map->getSpaceWidth() || $y < 0 || $y > $map->getSpaceHeight()) {
                $record->fail(\sprintf(
                    '"points[%d]" with the position (%s, %s) is outside the %d x %d pixel space of the map',
                    $index,
                    (string) $x,
                    (string) $y,
                    $map->getSpaceWidth(),
                    $map->getSpaceHeight(),
                ));
            }

            $vertices[] = ['x' => (float) $x, 'y' => (float) $y];
        }

        if (\count($vertices) < 2) {
            $record->fail('"points" needs at least two vertices to draw a line');
        }

        return $vertices;
    }
}
