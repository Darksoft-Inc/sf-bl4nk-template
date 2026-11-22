<?php

namespace App\Reference;

/**
 * Declares one generated page of the reference section for the service
 * class it sits on. The compiler pass collects every declared page,
 * validates it, and fails the build on any problem, so a page cannot
 * go stale silently. See docs/DECISIONS.md for the format rules.
 *
 * The slug names the stable URL segment: lowercase letters and digits
 * in segments separated by hyphens or dots, for example
 * "login-audit-retention" or "files.upload-limits". A dot is pure
 * naming convention: the registry keeps the slug opaque and never
 * groups or links pages from it, grouping is the job of group.
 */
// Repeatable on purpose: a duplicated declaration must fail the build
// with a message that names the class, not crash the engine at
// newInstance() nor let the first declaration win silently.
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class Reference
{
    /**
     * The slug rule every page follows, for the compiler pass that
     * validates a slug with preg_match().
     */
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:[.-][a-z0-9]+)*$/';

    /**
     * The same rule as a route requirement fragment, without the
     * delimiters and anchors a route regex cannot embed.
     */
    public const SLUG_REQUIREMENT = '[a-z0-9]+(?:[.-][a-z0-9]+)*';

    /**
     * @param array<int, mixed> $details the labeled facts of the page, in reading order; the compiler pass rejects an entry that is not a ReferenceDetail
     * @param array<int, mixed> $related slugs of other pages, shown as links at the bottom; the compiler pass rejects an entry that is not a string
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly string $summary,
        public readonly string $group = '',
        public readonly array $details = [],
        public readonly array $related = [],
    ) {
    }
}
