<?php

namespace App\Reference;

/**
 * The generated reference pages, collected and validated at build
 * time. The compiler pass hands over a plain array shape, because a
 * compiled container cannot carry objects as arguments, and this
 * class rebuilds the page objects from it. Nothing here reads the
 * disk or the container at runtime.
 */
final class ReferenceRegistry
{
    /** @var array<string, ReferencePage> */
    private array $pages = [];

    /**
     * @param list<array{slug: string, title: string, summary: string, group: string, details: list<array{label: string, text: string}>, related: list<string>}> $pages
     */
    public function __construct(array $pages)
    {
        foreach ($pages as $page) {
            $this->pages[$page['slug']] = new ReferencePage(
                $page['slug'],
                $page['title'],
                $page['summary'],
                $page['group'],
                array_map(
                    static fn (array $detail): ReferenceDetail => new ReferenceDetail($detail['label'], $detail['text']),
                    $page['details'],
                ),
                $page['related'],
            );
        }
    }

    public function find(string $slug): ?ReferencePage
    {
        return $this->pages[$slug] ?? null;
    }

    /**
     * The pages grouped by group name, groups and pages in
     * alphabetical order. The empty group sorts first and renders
     * without a heading.
     *
     * @return array<string, list<ReferencePage>>
     */
    public function grouped(): array
    {
        $groups = [];
        foreach ($this->pages as $page) {
            $groups[$page->group][] = $page;
        }

        ksort($groups);

        foreach ($groups as &$pages) {
            usort($pages, static fn (ReferencePage $a, ReferencePage $b): int => strcasecmp($a->title, $b->title));
        }

        return $groups;
    }
}
