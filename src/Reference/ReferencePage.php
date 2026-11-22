<?php

namespace App\Reference;

/**
 * One page of the reference section, built by the compiler pass from
 * the Reference attribute of a service. The %parameter% citations are
 * already resolved and the class constants are already concatenated,
 * so the pages read it without touching the container.
 */
final class ReferencePage
{
    /**
     * @param list<ReferenceDetail> $details
     * @param list<string>          $related
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly string $summary,
        public readonly string $group,
        public readonly array $details,
        public readonly array $related,
    ) {
    }
}
