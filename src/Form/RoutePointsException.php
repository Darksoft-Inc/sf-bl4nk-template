<?php

namespace App\Form;

/**
 * Thrown when route point text cannot be parsed. Carries the validators
 * domain message key and its parameters, so the form renders a translated
 * violation and the controller can store without revalidating.
 */
final class RoutePointsException extends \InvalidArgumentException
{
    /**
     * @param array<string, string> $parameters
     */
    public function __construct(
        public readonly string $messageKey,
        public readonly array $parameters = [],
    ) {
        parent::__construct($messageKey);
    }
}
