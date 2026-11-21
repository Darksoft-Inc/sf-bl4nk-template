<?php

namespace App\Uploads;

/**
 * One category of uploads. Each kind owns its storage location and its own
 * rules, and knows how to remove a file together with the field that
 * references it.
 */
interface UploadKindInterface
{
    /**
     * The kind name used in routes and templates.
     */
    public function getName(): string;

    /**
     * @return list<UploadedFile>
     */
    public function list(): array;

    /**
     * Removes the file and clears the field or relation that references it.
     *
     * @throws \InvalidArgumentException when the name is not a valid file of this kind
     */
    public function remove(string $filename): void;
}
