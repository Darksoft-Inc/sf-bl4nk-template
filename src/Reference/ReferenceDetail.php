<?php

namespace App\Reference;

/**
 * One labeled fact of a reference page: the label names the fact, the
 * text states it. The text is where a page cites the values that must
 * stay true, either by concatenating a class constant in the attribute
 * argument itself, or by naming a container parameter wrapped in
 * %...%, which the compiler pass resolves.
 */
final class ReferenceDetail
{
    public function __construct(
        public readonly string $label,
        public readonly string $text,
    ) {
    }
}
