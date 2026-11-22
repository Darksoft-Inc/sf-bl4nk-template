<?php

namespace App\Text;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * The app_markdown filter renders member-authored markdown. Its
 * output is declared html-safe because MarkdownRenderer strips raw
 * HTML and unsafe link targets: the filter result is the only raw
 * HTML the templates emit.
 */
final class MarkdownExtension extends AbstractExtension
{
    public function __construct(private readonly MarkdownRenderer $markdown)
    {
    }

    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('app_markdown', $this->markdown->toHtml(...), ['is_safe' => ['html']]),
        ];
    }
}
