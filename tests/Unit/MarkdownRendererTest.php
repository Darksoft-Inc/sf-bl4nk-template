<?php

namespace App\Tests\Unit;

use App\Text\MarkdownRenderer;
use PHPUnit\Framework\TestCase;

final class MarkdownRendererTest extends TestCase
{
    private MarkdownRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new MarkdownRenderer();
    }

    public function testRendersCoreSyntaxAndTables(): void
    {
        $html = $this->renderer->toHtml(<<<'MD'
            ### Heading

            A **bold** word, an *italic* one, and `code`.

            - one
            - two

            [link](https://example.com)

            | a | b |
            |---|---|
            | 1 | 2 |
            MD);

        self::assertStringContainsString('<h3>Heading</h3>', $html);
        self::assertStringContainsString('<strong>bold</strong>', $html);
        self::assertStringContainsString('<em>italic</em>', $html);
        self::assertStringContainsString('<code>code</code>', $html);
        self::assertStringContainsString('<li>one</li>', $html);
        self::assertStringContainsString('<li>two</li>', $html);
        self::assertStringContainsString('<a href="https://example.com">link</a>', $html);
        self::assertStringContainsString('<table>', $html);
    }

    public function testEscapesRawHtmlIntoLiteralText(): void
    {
        $html = $this->renderer->toHtml('Before <b>bold</b> after.');

        self::assertStringContainsString('Before', $html);
        self::assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $html);
        self::assertStringNotContainsString('<b>', $html);
    }

    public function testDropsUnsafeLinkTargets(): void
    {
        $html = $this->renderer->toHtml('A [link](javascript:alert(1)) and ![an image](data:text/html;base64,xxx).');

        self::assertStringNotContainsStringIgnoringCase('javascript:', $html);
        self::assertStringNotContainsStringIgnoringCase('data:', $html);
        self::assertStringContainsString('link', $html);
        self::assertStringContainsString('an image', $html);
    }
}
