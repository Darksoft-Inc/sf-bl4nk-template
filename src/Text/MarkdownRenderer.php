<?php

namespace App\Text;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use League\CommonMark\Util\HtmlFilter;

/**
 * Renders member-authored markdown into the HTML the pages emit raw.
 * The two policies live here and are locked by tests: raw HTML is
 * escaped into visible literal text, never rendered and never silently
 * swallowed with its line, and an unsafe link target (javascript:,
 * data:) loses its attribute in the output. See docs/DECISIONS.md for
 * the choice of league/commonmark and the flavor.
 */
final class MarkdownRenderer
{
    private readonly GithubFlavoredMarkdownConverter $converter;

    public function __construct()
    {
        $this->converter = new GithubFlavoredMarkdownConverter([
            'html_input' => HtmlFilter::ESCAPE,
            'allow_unsafe_links' => false,
        ]);
    }

    public function toHtml(string $markdown): string
    {
        return $this->converter->convert($markdown)->getContent();
    }
}
