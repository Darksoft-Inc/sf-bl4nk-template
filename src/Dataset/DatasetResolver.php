<?php

namespace App\Dataset;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Resolves a "@type.slug" reference to an entity of the current load.
 *
 * A record written in the current batch resolves to its entity. A record
 * written in an earlier batch resolves to a reference proxy built from
 * the row id harvested at the batch flush, because the flushed and
 * cleared entity manager no longer manages the entity itself. A record
 * of an earlier load resolves the same way, from the id the first pass
 * found in the database.
 */
final class DatasetResolver
{
    /** @var array<string, array<string, object>> */
    private array $entities = [];

    /** @var array<string, int> dataset key "type.slug" => row id */
    private array $ids = [];

    /**
     * @param array<string, DatasetTypeInterface> $types
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly array $types,
    ) {
    }

    public function add(string $type, string $slug, object $entity): void
    {
        $this->entities[$type][$slug] = $entity;
    }

    public function addId(string $type, string $slug, int $id): void
    {
        $this->ids[$type.'.'.$slug] = $id;
    }

    /**
     * Called after a batch flush: keeps the row ids of the batch as
     * lightweight references and drops the entities, which the cleared
     * entity manager no longer manages.
     */
    public function forgetEntities(): void
    {
        foreach ($this->entities as $type => $bySlug) {
            $metadata = $this->em->getClassMetadata($this->types[$type]->getEntityClass());

            foreach ($bySlug as $slug => $entity) {
                $identifier = $metadata->getIdentifierValues($entity);
                $id = $identifier[array_key_first($identifier)] ?? null;

                if (\is_int($id)) {
                    $this->ids[$type.'.'.$slug] = $id;
                }
            }
        }

        $this->entities = [];
    }

    /**
     * @throws \LogicException when the pair was neither written in this
     *                         load nor found in the database by the first pass
     */
    public function get(string $type, string $slug): object
    {
        $entity = $this->entities[$type][$slug] ?? null;

        if (null !== $entity) {
            return $entity;
        }

        $id = $this->ids[$type.'.'.$slug] ?? null;

        if (null !== $id) {
            $reference = $this->em->getReference($this->types[$type]->getEntityClass(), $id);

            if (null === $reference) {
                throw new \LogicException(\sprintf('The row of the record "@%s.%s" cannot be turned into a reference.', $type, $slug));
            }

            return $reference;
        }

        throw new \LogicException(\sprintf('The record "@%s.%s" is neither in this load nor in the database.', $type, $slug));
    }
}
