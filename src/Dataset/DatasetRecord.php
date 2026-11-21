<?php

namespace App\Dataset;

/**
 * One record from a dataset file, with the context its messages need:
 * every failure reports the file, the type section, and the slug.
 */
final class DatasetRecord
{
    public const SLUG_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';
    public const REFERENCE_PATTERN = '/^@([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)$/';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly string $type,
        public readonly string $slug,
        public readonly string $file,
        public readonly array $data,
    ) {
    }

    public function has(string $field): bool
    {
        return \array_key_exists($field, $this->data);
    }

    public function value(string $field): mixed
    {
        return $this->data[$field] ?? null;
    }

    public function string(string $field): string
    {
        $value = $this->data[$field] ?? null;

        if (!\is_string($value) || '' === $value) {
            $this->fail(\sprintf('"%s" must be a non-empty string, %s given.', $field, get_debug_type($value)));
        }

        return $value;
    }

    public function nullableString(string $field): ?string
    {
        $value = $this->data[$field] ?? null;

        if (null !== $value && !\is_string($value)) {
            $this->fail(\sprintf('"%s" must be a string or null, %s given.', $field, get_debug_type($value)));
        }

        return $value;
    }

    public function integer(string $field): int
    {
        $value = $this->data[$field] ?? null;

        if (!\is_int($value)) {
            $this->fail(\sprintf('"%s" must be an integer, %s given.', $field, get_debug_type($value)));
        }

        return $value;
    }

    public function float(string $field): float
    {
        $value = $this->data[$field] ?? null;

        if (!\is_int($value) && !\is_float($value)) {
            $this->fail(\sprintf('"%s" must be a number, %s given.', $field, get_debug_type($value)));
        }

        return (float) $value;
    }

    public function boolean(string $field): bool
    {
        $value = $this->data[$field] ?? null;

        if (!\is_bool($value)) {
            $this->fail(\sprintf('"%s" must be true or false, %s given.', $field, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    public function strings(string $field): array
    {
        $value = $this->data[$field] ?? null;

        if (!\is_array($value) || !array_is_list($value)) {
            $this->fail(\sprintf('"%s" must be a list of strings, %s given.', $field, get_debug_type($value)));
        }

        $strings = [];
        foreach ($value as $item) {
            if (!\is_string($item) || '' === $item) {
                $this->fail(\sprintf('"%s" must contain non-empty strings only, %s found.', $field, get_debug_type($item)));
            }

            $strings[] = $item;
        }

        return $strings;
    }

    public function dateTime(string $field): \DateTimeImmutable
    {
        $value = $this->data[$field] ?? null;

        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTime) {
            return \DateTimeImmutable::createFromMutable($value);
        }

        if (\is_string($value)) {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Exception) {
                $this->fail(\sprintf('"%s" cannot be parsed as a date: "%s".', $field, $value));
            }
        }

        $this->fail(\sprintf('"%s" must be a date, %s given.', $field, get_debug_type($value)));
    }

    /**
     * Returns the entity of the record this field references.
     */
    public function entity(string $field, string $type, DatasetResolver $resolver): object
    {
        $value = $this->data[$field] ?? null;

        if (!\is_string($value) || 1 !== preg_match(self::REFERENCE_PATTERN, $value, $match)) {
            $this->fail(\sprintf('"%s" must be a reference like "@%s.example".', $field, $type));
        }

        if ($match[1] !== $type) {
            $this->fail(\sprintf('"%s" must reference a "%s" record, "%s" given.', $field, $type, $value));
        }

        return $resolver->get($match[1], $match[2]);
    }

    /**
     * Reports a failure with the record context and stops the load.
     */
    public function fail(string $message): never
    {
        throw new DatasetException(\sprintf('[%s] %s.%s: %s', $this->file, $this->type, $this->slug, $message));
    }
}
