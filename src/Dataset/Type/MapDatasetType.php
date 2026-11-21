<?php

namespace App\Dataset\Type;

use App\Dataset\DatasetRecord;
use App\Dataset\DatasetResolver;
use App\Dataset\DatasetTypeInterface;
use App\Entity\Map;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * The "maps" section. The image size is named as two fields, because the
 * entity stores it as one pair: naming one without the other is an error
 * rather than a silent half change.
 */
#[AutoconfigureTag('app.dataset_type')]
final class MapDatasetType implements DatasetTypeInterface
{
    private const FIELDS = ['name', 'createdAt', 'imageFilename', 'imageWidth', 'imageHeight', 'minZoom', 'maxZoom'];

    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    public function getName(): string
    {
        return 'maps';
    }

    public function getEntityClass(): string
    {
        return Map::class;
    }

    public function getFieldNames(): array
    {
        return self::FIELDS;
    }

    public function getRequiredFieldNames(): array
    {
        return ['name'];
    }

    public function create(DatasetRecord $record, DatasetResolver $resolver): object
    {
        $createdAt = $record->has('createdAt') ? $record->dateTime('createdAt') : $this->clock->now();

        $map = new Map($record->string('name'), $createdAt);
        $map->setDatasetSlug($record->slug);

        $this->apply($map, $record);

        return $map;
    }

    public function update(object $entity, DatasetRecord $record, DatasetResolver $resolver): void
    {
        \assert($entity instanceof Map);

        $this->apply($entity, $record);
    }

    private function apply(Map $map, DatasetRecord $record): void
    {
        if ($record->has('name')) {
            $map->setName($record->string('name'));
        }

        if ($record->has('createdAt')) {
            $map->setCreatedAt($record->dateTime('createdAt'));
        }

        if ($record->has('imageFilename')) {
            $map->setImageFilename($record->nullableString('imageFilename'));
        }

        if ($record->has('imageWidth') || $record->has('imageHeight')) {
            if (!$record->has('imageWidth') || !$record->has('imageHeight')) {
                $record->fail('"imageWidth" and "imageHeight" must be named together');
            }

            $map->setImageSize($record->integer('imageWidth'), $record->integer('imageHeight'));
        }

        if ($record->has('minZoom')) {
            $map->setMinZoom($record->integer('minZoom'));
        }

        if ($record->has('maxZoom')) {
            $maxZoom = $record->integer('maxZoom');

            if ($maxZoom < $map->getMinZoom()) {
                $record->fail(\sprintf('"maxZoom" (%d) is below "minZoom" (%d)', $maxZoom, $map->getMinZoom()));
            }

            $map->setMaxZoom($maxZoom);
        }
    }
}
