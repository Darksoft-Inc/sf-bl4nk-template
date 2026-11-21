<?php

namespace App\Form;

/**
 * Parses the route textarea: one "x, y" pair per line in the pixel space of
 * the map. The admin form validates with the same parse the controller uses
 * to store the line, so the text and the stored points cannot disagree.
 */
final class RoutePoints
{
    /**
     * @return list<array{x: float, y: float}>
     *
     * @throws RoutePointsException when the text cannot be a route line
     */
    public static function parse(string $text, int $spaceWidth, int $spaceHeight): array
    {
        $points = [];
        $lineNumber = 0;

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            ++$lineNumber;

            $line = trim($line);

            if ('' === $line) {
                continue;
            }

            $coordinates = preg_split('/[\s,]+/', $line) ?: [];

            if (2 !== \count($coordinates) || !is_numeric($coordinates[0]) || !is_numeric($coordinates[1])) {
                throw new RoutePointsException('app.map_route.points_invalid_pair', ['%line%' => (string) $lineNumber]);
            }

            $x = (float) $coordinates[0];
            $y = (float) $coordinates[1];

            if ($x < 0 || $x > $spaceWidth || $y < 0 || $y > $spaceHeight) {
                throw new RoutePointsException('app.map_route.points_out_of_space', ['%line%' => (string) $lineNumber]);
            }

            $points[] = ['x' => $x, 'y' => $y];
        }

        if (\count($points) < 2) {
            throw new RoutePointsException('app.map_route.points_too_few');
        }

        return $points;
    }

    /**
     * @param list<array{x: float, y: float}> $points
     */
    public static function format(array $points): string
    {
        $lines = [];
        foreach ($points as $point) {
            $lines[] = self::formatNumber($point['x']).', '.self::formatNumber($point['y']);
        }

        return implode("\n", $lines);
    }

    private static function formatNumber(float $number): string
    {
        return (string) ($number == (int) $number ? (int) $number : $number);
    }
}
