<?php

namespace App\Form;

/**
 * The symbols field of a point: ux-icons names separated by whitespace.
 * The point form and the admin controller share the same parse, so the
 * names the form accepts and the names the controller stores cannot
 * disagree.
 */
final class Symbols
{
    /**
     * @return list<string>
     */
    public static function parse(string $text): array
    {
        return array_values(array_filter(
            preg_split('/\s+/', trim($text)) ?: [],
            static fn (string $name): bool => '' !== $name,
        ));
    }

    /**
     * @param list<string> $symbols
     */
    public static function format(array $symbols): string
    {
        return implode(' ', $symbols);
    }
}
