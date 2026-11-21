<?php

namespace App\Dataset\Type;

use App\Dataset\DatasetRecord;
use App\Dataset\DatasetResolver;
use App\Dataset\DatasetTypeInterface;
use App\Entity\Map;
use App\Entity\MapRouteType;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * The "map_route_types" section of a map.
 */
#[AutoconfigureTag('app.dataset_type')]
final class MapRouteTypeDatasetType implements DatasetTypeInterface
{
    private const FIELDS = ['map', 'name', 'color'];

    private const COLOR_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    public function getName(): string
    {
        return 'map_route_types';
    }

    public function getEntityClass(): string
    {
        return MapRouteType::class;
    }

    public function getFieldNames(): array
    {
        return self::FIELDS;
    }

    public function getRequiredFieldNames(): array
    {
        return self::FIELDS;
    }

    public function create(DatasetRecord $record, DatasetResolver $resolver): object
    {
        $routeType = new MapRouteType($this->map($record, $resolver), $record->string('name'), $this->color($record));
        $routeType->setDatasetSlug($record->slug);

        $this->apply($routeType, $record, $resolver);

        return $routeType;
    }

    public function update(object $entity, DatasetRecord $record, DatasetResolver $resolver): void
    {
        \assert($entity instanceof MapRouteType);

        $this->apply($entity, $record, $resolver);
    }

    private function apply(MapRouteType $routeType, DatasetRecord $record, DatasetResolver $resolver): void
    {
        if ($record->has('map')) {
            $routeType->setMap($this->map($record, $resolver));
        }

        if ($record->has('name')) {
            $routeType->setName($record->string('name'));
        }

        if ($record->has('color')) {
            $routeType->setColor($this->color($record));
        }
    }

    private function map(DatasetRecord $record, DatasetResolver $resolver): Map
    {
        $map = $record->entity('map', 'maps', $resolver);
        \assert($map instanceof Map);

        return $map;
    }

    private function color(DatasetRecord $record): string
    {
        $color = $record->string('color');

        if (1 !== preg_match(self::COLOR_PATTERN, $color)) {
            $record->fail(\sprintf('the color "%s" is not a "#rrggbb" hex color', $color));
        }

        return $color;
    }
}
