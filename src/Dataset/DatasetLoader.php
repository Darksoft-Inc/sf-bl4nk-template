<?php

namespace App\Dataset;

use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads YAML dataset files into the database. Records are keyed by their
 * dataset slug: a slug new to the database inserts a row, a known slug
 * updates the fields the record names, and rows whose slug column is null
 * are never touched. Relations between records are written as
 * "@type.slug" references and resolved at load time, either against the
 * current load or against a row of an earlier load, so a subset of a
 * dataset loads on its own.
 *
 * The load runs in two passes and one transaction. The first pass reads
 * every file and reduces it to a light index: record identity, file, and
 * references; it validates the structure, the references, and the
 * cycles, so every structural failure happens before any write. The
 * second pass streams the files again and writes in batches: each full
 * batch is flushed and the entity manager cleared, so memory holds one
 * file, one batch, and the index, not the whole dataset. Any failure
 * rolls the transaction back and leaves the database unchanged.
 */
final class DatasetLoader
{
    public const DEFAULT_BATCH_SIZE = 100;

    /** @var array<string, DatasetTypeInterface> */
    private readonly array $types;

    /**
     * @param iterable<DatasetTypeInterface> $types
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[AutowireIterator('app.dataset_type')]
        iterable $types,
    ) {
        $byName = [];
        foreach ($types as $type) {
            if (isset($byName[$type->getName()])) {
                throw new \LogicException(\sprintf('Two dataset types share the name "%s".', $type->getName()));
            }

            $byName[$type->getName()] = $type;
        }

        $this->types = $byName;
    }

    /**
     * Loads every YAML file under the given paths. A path is a dataset
     * file or a directory; a directory contributes its YAML files in
     * name order.
     *
     * @param list<string> $paths
     *
     * @throws DatasetException when a file is invalid or the database rejects the data
     */
    public function load(array $paths, bool $purge = false, bool $dryRun = false, int $batchSize = self::DEFAULT_BATCH_SIZE): DatasetResult
    {
        if ($batchSize < 1) {
            throw new DatasetException('The batch size must be 1 or more.');
        }

        $files = $this->findFiles($paths);
        [$definitions, $removals] = $this->buildIndex($files);
        $this->assertRemovalsAreNotDefined($removals, $definitions);
        $external = $this->validateReferences($definitions, $removals, $purge);

        $result = new DatasetResult(\count($files));

        $resolver = new DatasetResolver($this->em, $this->types);
        foreach ($external as [$type, $slug, $id]) {
            $resolver->addId($type, $slug, $id);
        }

        $this->em->beginTransaction();
        try {
            // A previous load in the same process may have left managed
            // entities in the identity map. Their inverse collections
            // hold in-memory state instead of the database rows, so the
            // removals and the lookups below read fresh instances.
            $this->em->clear();

            if ($purge) {
                $result->addPurged($this->purgeSluggedRows());
            }

            $this->removeRecords($removals, $batchSize, $result);
            $this->writeRecords($files, $definitions, $resolver, $batchSize, $result);
            $this->em->flush();

            if ($dryRun) {
                $this->em->rollback();
            } else {
                $this->em->commit();
            }
        } catch (\Throwable $e) {
            if ($this->em->getConnection()->isTransactionActive()) {
                $this->em->rollback();
            }

            if ($e instanceof DbalException) {
                throw new DatasetException('The database rejected the dataset: '.$e->getMessage(), 0, $e);
            }

            throw $e;
        }

        return $result;
    }

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function findFiles(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (\is_file($path)) {
                if (!str_ends_with($path, '.yaml') && !str_ends_with($path, '.yml')) {
                    throw new DatasetException(\sprintf('The dataset file "%s" must end in ".yaml" or ".yml".', $path));
                }

                $files[] = $path;
            } elseif (\is_dir($path)) {
                $finder = Finder::create()
                    ->in($path)
                    ->files()
                    ->depth('== 0')
                    ->name('*.yaml')
                    ->name('*.yml')
                    ->sortByName();

                foreach ($finder as $file) {
                    $files[] = $file->getPathname();
                }
            } else {
                throw new DatasetException(\sprintf('The dataset path "%s" does not exist.', $path));
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * Parses one file and returns its records by type section and slug.
     * Validated here, so both passes can trust the shape: the sections
     * name known types, the slugs are well formed, and the field keys are
     * strings.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function parseFile(string $file): array
    {
        try {
            $content = Yaml::parseFile($file, Yaml::PARSE_DATETIME);
        } catch (ParseException $e) {
            throw new DatasetException(\sprintf('The dataset file "%s" is not valid YAML: %s', $file, $e->getMessage()), 0, $e);
        }

        if (!\is_array($content)) {
            throw new DatasetException(\sprintf('The dataset file "%s" must contain a mapping of type names to records.', $file));
        }

        $sections = [];

        foreach ($content as $type => $records) {
            $type = (string) $type;

            if (!isset($this->types[$type])) {
                throw new DatasetException(\sprintf('The dataset file "%s" uses the unknown type "%s". Known types: %s.', $file, $type, implode(', ', array_keys($this->types))));
            }

            if (!\is_array($records)) {
                throw new DatasetException(\sprintf('The "%s" section of "%s" must be a mapping of slugs to records.', $type, $file));
            }

            $bySlug = [];

            foreach ($records as $slug => $record) {
                if (!\is_string($slug) || 1 !== preg_match(DatasetRecord::SLUG_PATTERN, $slug)) {
                    throw new DatasetException(\sprintf('The record key "%s" in the "%s" section of "%s" is not a valid slug: letters, digits, "_" and "-" only.', (string) $slug, $type, $file));
                }

                if (!\is_array($record)) {
                    throw new DatasetException(\sprintf('[%s] %s.%s: the record must be a mapping of field names to values.', $file, $type, $slug));
                }

                $fields = [];
                foreach ($record as $field => $value) {
                    if (!\is_string($field)) {
                        throw new DatasetException(\sprintf('[%s] %s.%s: the field key "%s" must be a string.', $file, $type, $slug, (string) $field));
                    }

                    $fields[$field] = $value;
                }

                $bySlug[$slug] = $fields;
            }

            $sections[$type] = $bySlug;
        }

        return $sections;
    }

    /**
     * First pass: reads every file once and reduces it to the index,
     * without keeping the record data, and validates the structure:
     * duplicate slugs, the shape of removal entries, and the field
     * names.
     *
     * @param list<string> $files
     *
     * @return array{0: array<string, array<string, DatasetIndexEntry>>, 1: list<DatasetIndexEntry>}
     */
    private function buildIndex(array $files): array
    {
        $definitions = [];
        $removals = [];

        foreach ($files as $file) {
            foreach ($this->parseFile($file) as $type => $records) {
                foreach ($records as $slug => $fields) {
                    $entry = new DatasetIndexEntry($type, $slug, $file, $this->referencesOf($fields));

                    if (\array_key_exists('remove', $fields)) {
                        if (true !== $fields['remove']) {
                            $entry->fail('the "remove" flag must be true; drop the entry to leave the row in place');
                        }

                        if (\count($fields) > 1) {
                            $entry->fail('a removal entry carries only the "remove" flag, because only the slug identifies the row');
                        }

                        $removals[] = $entry;

                        continue;
                    }

                    $this->assertKnownFields($entry, $fields);

                    if (isset($definitions[$type][$slug])) {
                        $entry->fail(\sprintf('the slug is already defined in [%s].', $definitions[$type][$slug]->file));
                    }

                    $definitions[$type][$slug] = $entry;
                }
            }
        }

        return [$definitions, $removals];
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function assertKnownFields(DatasetIndexEntry $entry, array $fields): void
    {
        $known = $this->types[$entry->type]->getFieldNames();

        foreach (array_keys($fields) as $field) {
            if (!\in_array($field, $known, true)) {
                $entry->fail(\sprintf('unknown field "%s". Known fields: %s.', (string) $field, implode(', ', $known)));
            }
        }
    }

    /**
     * @param list<DatasetIndexEntry>                         $removals
     * @param array<string, array<string, DatasetIndexEntry>> $definitions
     */
    private function assertRemovalsAreNotDefined(array $removals, array $definitions): void
    {
        foreach ($removals as $removal) {
            if (isset($definitions[$removal->type][$removal->slug])) {
                $removal->fail('the slug is also defined as a record in this load; a record cannot be removed and written at once');
            }
        }
    }

    /**
     * Validates every reference before any write, and returns the row
     * ids of the external ones. Internal references must name a record
     * of the load; external references must name a row of an earlier
     * load, found by its dataset slug. A purge load must be
     * self-contained, because the purge deletes the rows an external
     * reference would point at.
     *
     * @param array<string, array<string, DatasetIndexEntry>> $definitions
     * @param list<DatasetIndexEntry>                         $removals
     *
     * @return list<array{0: string, 1: string, 2: int}> the (type, slug, id) of every external reference
     */
    private function validateReferences(array $definitions, array $removals, bool $purge): array
    {
        $removalKeys = [];
        foreach ($removals as $removal) {
            $removalKeys[$removal->type.'.'.$removal->slug] = true;
        }

        $edges = [];
        $external = [];

        foreach ($definitions as $bySlug) {
            foreach ($bySlug as $entry) {
                $targets = [];

                foreach ($entry->references as [$type, $slug, $raw]) {
                    if (!isset($this->types[$type])) {
                        $entry->fail(\sprintf('the reference "%s" names the unknown type "%s". Known types: %s.', $raw, $type, implode(', ', array_keys($this->types))));
                    }

                    if (isset($removalKeys[$type.'.'.$slug])) {
                        $entry->fail(\sprintf('the reference "%s" points at a record marked for removal in this load.', $raw));
                    }

                    if (isset($definitions[$type][$slug])) {
                        $targets[] = $type.'.'.$slug;

                        continue;
                    }

                    $external[$type.'.'.$slug] ??= [$type, $slug, $entry];
                }

                $edges[$entry->type.'.'.$entry->slug] = array_values(array_unique($targets));
            }
        }

        if ([] !== $external && $purge) {
            [$type, $slug, $entry] = reset($external);
            $entry->fail(\sprintf(
                'the reference "@%s.%s" targets a row of an earlier load, but the purge deletes it first; include the record in this load instead',
                $type,
                $slug,
            ));
        }

        $ids = [];
        foreach ($external as [$type, $slug, $entry]) {
            $class = $this->types[$type]->getEntityClass();
            $entity = $this->em->getRepository($class)->findOneBy(['datasetSlug' => $slug]);

            if (null === $entity) {
                $entry->fail(\sprintf(
                    'unknown reference "@%s.%s": neither a record in this load nor a row with this dataset slug exists in the database',
                    $type,
                    $slug,
                ));
            }

            $ids[] = [$type, $slug, $this->identifierOf($this->types[$type], $entity)];
        }

        $this->detectCycles($definitions, $edges);

        return $ids;
    }

    /**
     * Fails before any write when the internal references form a cycle.
     *
     * @param array<string, array<string, DatasetIndexEntry>> $definitions
     * @param array<string, list<string>>                     $edges
     */
    private function detectCycles(array $definitions, array $edges): void
    {
        $count = 0;
        foreach ($definitions as $bySlug) {
            $count += \count($bySlug);
        }

        $indegree = [];
        $dependents = [];
        foreach ($edges as $key => $targets) {
            $indegree[$key] = \count($targets);

            foreach ($targets as $target) {
                $dependents[$target][] = $key;
            }
        }

        $placed = 0;
        $ready = array_keys(array_filter($indegree, static fn (int $dependencies): bool => 0 === $dependencies));

        while ([] !== $ready) {
            $key = array_shift($ready);
            ++$placed;

            foreach ($dependents[$key] ?? [] as $dependent) {
                if (0 === --$indegree[$dependent]) {
                    $ready[] = $dependent;
                }
            }
        }

        if ($placed !== $count) {
            $stuck = array_keys(array_filter($indegree, static fn (int $dependencies): bool => $dependencies > 0));

            throw new DatasetException(\sprintf('Circular references among: %s.', implode(', ', $stuck)));
        }
    }

    /**
     * Second pass: streams the files again and writes the records in
     * batches. A record is written when every internal reference has
     * been written; records read too early wait in the pending list
     * until the file holding their dependency is read. Each full batch
     * is flushed and cleared, so memory holds one file, one batch, and
     * the index instead of the whole dataset.
     *
     * @param list<string>                                    $files
     * @param array<string, array<string, DatasetIndexEntry>> $definitions
     */
    private function writeRecords(array $files, array $definitions, DatasetResolver $resolver, int $batchSize, DatasetResult $result): void
    {
        $internal = [];
        foreach ($definitions as $bySlug) {
            foreach ($bySlug as $slug => $entry) {
                $internal[$entry->type.'.'.$slug] = true;
            }
        }

        $placed = [];
        $pending = [];
        $written = 0;

        $write = function (DatasetRecord $record) use (&$placed, &$written, $resolver, $result, $batchSize): void {
            $this->processRecord($record, $resolver, $result);
            $placed[$record->type.'.'.$record->slug] = true;
            ++$written;

            if (0 === $written % $batchSize) {
                $this->em->flush();
                $this->em->clear();
                $resolver->forgetEntities();
            }
        };

        foreach ($files as $file) {
            foreach ($this->parseFile($file) as $type => $records) {
                foreach ($records as $slug => $fields) {
                    if (\array_key_exists('remove', $fields)) {
                        continue;
                    }

                    $record = new DatasetRecord($type, $slug, $file, $fields);

                    if ($this->isReady($record, $internal, $placed)) {
                        $write($record);
                    } else {
                        $pending[] = $record;
                    }
                }
            }

            $pending = $this->drainPending($pending, $internal, $placed, $write);
        }

        while ([] !== $pending) {
            $before = \count($pending);
            $pending = $this->drainPending($pending, $internal, $placed, $write);

            if (\count($pending) === $before) {
                throw new \LogicException('Records are still blocked after every file was read; the first pass validated the reference graph.');
            }
        }
    }

    /**
     * @param list<DatasetRecord>           $pending
     * @param array<string, true>           $internal
     * @param array<string, true>           $placed
     * @param \Closure(DatasetRecord): void $write
     *
     * @return list<DatasetRecord> the records still waiting
     */
    private function drainPending(array $pending, array $internal, array $placed, \Closure $write): array
    {
        $remaining = [];

        foreach ($pending as $record) {
            if ($this->isReady($record, $internal, $placed)) {
                $write($record);
            } else {
                $remaining[] = $record;
            }
        }

        return $remaining;
    }

    /**
     * A record is ready when every record it references inside this load
     * has been written; references to earlier loads were validated
     * against the database in the first pass.
     *
     * @param array<string, true> $internal
     * @param array<string, true> $placed
     */
    private function isReady(DatasetRecord $record, array $internal, array $placed): bool
    {
        foreach ($this->referencesOf($record->data) as [$type, $slug]) {
            $key = $type.'.'.$slug;

            if (isset($internal[$key]) && !isset($placed[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Writes one ordered record: a known slug updates the entity, a new
     * slug creates one.
     */
    private function processRecord(DatasetRecord $record, DatasetResolver $resolver, DatasetResult $result): void
    {
        $type = $this->types[$record->type];
        $entity = $this->em->getRepository($type->getEntityClass())->findOneBy(['datasetSlug' => $record->slug]);

        if (null !== $entity) {
            $type->update($entity, $record, $resolver);
            $result->updated($record->type);
        } else {
            $this->assertRequiredFields($record);
            $entity = $type->create($record, $resolver);
            $this->em->persist($entity);
            $result->created($record->type);
        }

        $resolver->add($record->type, $record->slug, $entity);
    }

    private function assertRequiredFields(DatasetRecord $record): void
    {
        foreach ($this->types[$record->type]->getRequiredFieldNames() as $field) {
            if (!$record->has($field)) {
                $record->fail(\sprintf('the field "%s" is required when the slug is new; it updates only named fields on an existing row', $field));
            }
        }
    }

    /**
     * Collects the records one record references, whatever the field or
     * the nesting depth, because a reference is any string shaped
     * "@type.slug".
     *
     * @param array<string, mixed> $fields
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function referencesOf(array $fields): array
    {
        $references = [];

        $walk = static function (mixed $value) use (&$walk, &$references): void {
            if (\is_array($value)) {
                foreach ($value as $item) {
                    $walk($item);
                }

                return;
            }

            if (\is_string($value) && 1 === preg_match(DatasetRecord::REFERENCE_PATTERN, $value, $match)) {
                $references[] = [$match[1], $match[2], $value];
            }
        };

        $walk($fields);

        return $references;
    }

    /**
     * Deletes the rows named by the removal entries, children first, in
     * batches like the writes. A removal is a no-op when no row carries
     * the slug.
     *
     * @param list<DatasetIndexEntry> $removals
     */
    private function removeRecords(array $removals, int $batchSize, DatasetResult $result): void
    {
        if ([] === $removals) {
            return;
        }

        $byClass = [];
        foreach ($removals as $entry) {
            $byClass[$this->types[$entry->type]->getEntityClass()][] = $entry;
        }

        $removed = 0;

        foreach ($this->deleteOrder() as $class) {
            foreach ($byClass[$class] ?? [] as $entry) {
                $entity = $this->em->getRepository($class)->findOneBy(['datasetSlug' => $entry->slug]);

                if (null !== $entity) {
                    $this->em->remove($entity);
                    $result->removed($entry->type);
                    ++$removed;

                    if (0 === $removed % $batchSize) {
                        $this->em->flush();
                        $this->em->clear();
                    }
                }
            }
        }
    }

    /**
     * Deletes every row that carries a dataset slug, children first.
     */
    private function purgeSluggedRows(): int
    {
        $count = 0;

        foreach ($this->deleteOrder() as $class) {
            $deleted = $this->em->createQueryBuilder()
                ->delete($class, 'e')
                ->where('e.datasetSlug IS NOT NULL')
                ->getQuery()
                ->execute();

            if (\is_int($deleted)) {
                $count += $deleted;
            }
        }

        return $count;
    }

    /**
     * The managed classes ordered so every class that references another
     * is deleted before it: children first, because the referencing side
     * holds the foreign key.
     *
     * @return list<class-string>
     */
    private function deleteOrder(): array
    {
        $classes = [];
        foreach ($this->types as $type) {
            $class = $type->getEntityClass();
            if (!\in_array($class, $classes, true)) {
                $classes[] = $class;
            }
        }

        // Only the owning side carries the foreign key, so only it
        // constrains the delete order; inverse sides would form
        // artificial cycles.
        $referencing = [];
        foreach ($classes as $class) {
            foreach ($this->em->getClassMetadata($class)->getAssociationMappings() as $mapping) {
                if ($mapping->isOwningSide() && \in_array($mapping->targetEntity, $classes, true)) {
                    $referencing[$class][] = $mapping->targetEntity;
                }
            }
        }

        $referencedBy = [];
        foreach ($referencing as $class => $targets) {
            foreach ($targets as $target) {
                $referencedBy[$target][] = $class;
            }
        }

        $order = [];
        $remaining = $classes;

        while ([] !== $remaining) {
            $progress = false;

            foreach ($remaining as $index => $class) {
                if ([] === array_diff($referencedBy[$class] ?? [], $order)) {
                    $order[] = $class;
                    unset($remaining[$index]);
                    $progress = true;
                }
            }

            if (!$progress) {
                throw new DatasetException('The dataset types form a reference cycle, so no delete order exists.');
            }

            $remaining = array_values($remaining);
        }

        return $order;
    }

    private function identifierOf(DatasetTypeInterface $type, object $entity): int
    {
        $identifier = $this->em->getClassMetadata($type->getEntityClass())->getIdentifierValues($entity);
        $id = $identifier[array_key_first($identifier)] ?? null;

        if (!\is_int($id)) {
            throw new \LogicException(\sprintf('The entity of class "%s" carries no integer identifier.', $type->getEntityClass()));
        }

        return $id;
    }
}
