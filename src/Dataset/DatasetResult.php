<?php

namespace App\Dataset;

/**
 * What one load run wrote: created, updated, and removed records per type
 * section, plus the rows removed by the purge.
 */
final class DatasetResult
{
    /** @var array<string, int> */
    private array $created = [];

    /** @var array<string, int> */
    private array $updated = [];

    /** @var array<string, int> */
    private array $removed = [];

    private int $purged = 0;

    public function __construct(
        private readonly int $fileCount,
    ) {
    }

    public function created(string $type): void
    {
        $this->created[$type] = ($this->created[$type] ?? 0) + 1;
    }

    public function updated(string $type): void
    {
        $this->updated[$type] = ($this->updated[$type] ?? 0) + 1;
    }

    public function removed(string $type): void
    {
        $this->removed[$type] = ($this->removed[$type] ?? 0) + 1;
    }

    public function addPurged(int $count): void
    {
        $this->purged += $count;
    }

    public function fileCount(): int
    {
        return $this->fileCount;
    }

    public function purged(): int
    {
        return $this->purged;
    }

    /**
     * @return array<string, array{created: int, updated: int, removed: int}>
     */
    public function counts(): array
    {
        $types = array_unique([...array_keys($this->created), ...array_keys($this->updated), ...array_keys($this->removed)]);

        $counts = [];
        foreach ($types as $type) {
            $counts[$type] = [
                'created' => $this->created[$type] ?? 0,
                'updated' => $this->updated[$type] ?? 0,
                'removed' => $this->removed[$type] ?? 0,
            ];
        }

        return $counts;
    }
}
