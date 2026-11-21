<?php

namespace App\Uploads;

/**
 * One row of the admin upload list. The kind decides how each field is
 * filled, so kinds with different locations and rules render together.
 */
final class UploadedFile
{
    public function __construct(
        public readonly string $kind,
        public readonly string $filename,
        public readonly ?string $owner,
        public readonly ?string $url,
        public readonly int $size,
        public readonly \DateTimeImmutable $modifiedAt,
    ) {
    }
}
