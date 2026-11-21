<?php

namespace App\Dataset\Type;

use App\Dataset\DatasetRecord;
use App\Dataset\DatasetResolver;
use App\Dataset\DatasetTypeInterface;
use App\Entity\Map;
use App\Entity\MapPoint;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\UX\Icons\Exception\IconNotFoundException;
use Symfony\UX\Icons\IconRendererInterface;

/**
 * The "map_points" section. Positions are pixels in the space of the
 * referenced map, and every symbol must be a renderable ux-icons name,
 * the same rule the point form enforces.
 */
#[AutoconfigureTag('app.dataset_type')]
final class MapPointDatasetType implements DatasetTypeInterface
{
    private const FIELDS = ['map', 'title', 'x', 'y', 'description', 'symbols'];

    public function __construct(
        private readonly IconRendererInterface $iconRenderer,
    ) {
    }

    public function getName(): string
    {
        return 'map_points';
    }

    public function getEntityClass(): string
    {
        return MapPoint::class;
    }

    public function getFieldNames(): array
    {
        return self::FIELDS;
    }

    public function getRequiredFieldNames(): array
    {
        return ['map', 'title', 'x', 'y'];
    }

    public function create(DatasetRecord $record, DatasetResolver $resolver): object
    {
        $map = $this->map($record, $resolver);
        $x = $record->float('x');
        $y = $record->float('y');
        $this->assertWithinSpace($record, $map, $x, $y);

        $point = new MapPoint($map, $record->string('title'), $x, $y);
        $point->setDatasetSlug($record->slug);

        $this->apply($point, $record, $resolver);

        return $point;
    }

    public function update(object $entity, DatasetRecord $record, DatasetResolver $resolver): void
    {
        \assert($entity instanceof MapPoint);

        $this->apply($entity, $record, $resolver);
    }

    private function apply(MapPoint $point, DatasetRecord $record, DatasetResolver $resolver): void
    {
        if ($record->has('map')) {
            $point->setMap($this->map($record, $resolver));
        }

        if ($record->has('title')) {
            $point->setTitle($record->string('title'));
        }

        if ($record->has('x') || $record->has('y')) {
            $x = $record->has('x') ? $record->float('x') : $point->getX();
            $y = $record->has('y') ? $record->float('y') : $point->getY();
            $this->assertWithinSpace($record, $point->getMap(), $x, $y);

            $point->setX($x);
            $point->setY($y);
        }

        if ($record->has('description')) {
            $point->setDescription($record->nullableString('description'));
        }

        if ($record->has('symbols')) {
            $symbols = $record->strings('symbols');

            foreach ($symbols as $symbol) {
                $this->assertRenderableIcon($record, $symbol);
            }

            $point->setSymbols($symbols);
        }
    }

    private function map(DatasetRecord $record, DatasetResolver $resolver): Map
    {
        $map = $record->entity('map', 'maps', $resolver);
        \assert($map instanceof Map);

        return $map;
    }

    private function assertWithinSpace(DatasetRecord $record, Map $map, float $x, float $y): void
    {
        if ($x < 0 || $x > $map->getSpaceWidth() || $y < 0 || $y > $map->getSpaceHeight()) {
            $record->fail(\sprintf(
                'the position (%s, %s) is outside the %d x %d pixel space of the map',
                (string) $x,
                (string) $y,
                $map->getSpaceWidth(),
                $map->getSpaceHeight(),
            ));
        }
    }

    private function assertRenderableIcon(DatasetRecord $record, string $name): void
    {
        try {
            $this->iconRenderer->renderIcon($name);
        } catch (IconNotFoundException) {
            $record->fail(\sprintf('the symbol "%s" is not a renderable icon; lock it with "ux:icons:lock" or pick a locked name', $name));
        }
    }
}
