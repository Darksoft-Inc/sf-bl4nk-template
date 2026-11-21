<?php

namespace App\Dataset;

/**
 * One record reduced to what the first pass needs: its identity, the
 * file that holds it, and the records it references. The record data
 * itself is not kept, so the index of a large load stays small and the
 * second pass reads each file again.
 */
final class DatasetIndexEntry
{
    /**
     * @param list<array{0: string, 1: string, 2: string}> $references
     *                                                                 each a (referenced type, referenced slug, raw string) triple
     */
    public function __construct(
        public readonly string $type,
        public readonly string $slug,
        public readonly string $file,
        public readonly array $references,
    ) {
    }

    /**
     * Reports a failure with the record context and stops the load.
     */
    public function fail(string $message): never
    {
        throw new DatasetException(\sprintf('[%s] %s.%s: %s', $this->file, $this->type, $this->slug, $message));
    }
}
