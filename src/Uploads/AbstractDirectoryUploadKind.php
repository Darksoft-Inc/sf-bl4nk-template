<?php

namespace App\Uploads;

/**
 * The shared mechanics of a kind whose files live in one directory: the
 * directory listing of list(), and the name and existence checks that
 * guard a removal. A subclass names its directory, its file-name
 * pattern, and its label for messages, and decides who references a
 * file, what the public URL of a file is, and what a removal does with
 * the reference.
 */
abstract class AbstractDirectoryUploadKind implements UploadKindInterface
{
    /**
     * @return list<UploadedFile>
     */
    public function list(): array
    {
        $filenames = $this->filenames();

        if ([] === $filenames) {
            return [];
        }

        $owners = $this->ownersOf($filenames);

        $files = [];
        foreach ($filenames as $filename) {
            $path = $this->directory().'/'.$filename;

            $modifiedAt = filemtime($path);
            $size = filesize($path);
            \assert(false !== $modifiedAt && false !== $size);

            $files[] = new UploadedFile(
                $this->getName(),
                $filename,
                $owners[$filename] ?? null,
                $this->url($filename),
                $size,
                (new \DateTimeImmutable())->setTimestamp($modifiedAt),
            );
        }

        usort($files, static fn (UploadedFile $a, UploadedFile $b): int => strcmp($a->filename, $b->filename));

        return $files;
    }

    /**
     * The path of one file of this kind, after the name passed the kind
     * pattern and the file exists.
     *
     * @throws \InvalidArgumentException when the name is not a valid file of this kind
     */
    protected function existingFile(string $filename): string
    {
        if (!preg_match($this->filenamePattern(), $filename)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid %s file name.', $filename, $this->label()));
        }

        $path = $this->directory().'/'.$filename;

        if (!is_file($path)) {
            throw new \InvalidArgumentException(\sprintf('The %s file "%s" does not exist.', $this->label(), $filename));
        }

        return $path;
    }

    /**
     * The file names the directory holds, dot files excluded.
     *
     * @return list<string>
     */
    private function filenames(): array
    {
        if (!is_dir($this->directory())) {
            return [];
        }

        $names = scandir($this->directory());
        \assert(\is_array($names));

        return array_values(array_filter(
            $names,
            static fn (string $filename): bool => !str_starts_with($filename, '.'),
        ));
    }

    /**
     * The directory that holds the files of this kind.
     */
    abstract protected function directory(): string;

    /**
     * The pattern every file name of this kind matches, validated before
     * any file operation, so no path fragment can reach the disk.
     */
    abstract protected function filenamePattern(): string;

    /**
     * The kind label used in the error messages.
     */
    abstract protected function label(): string;

    /**
     * Describes who references each file, in one query per kind.
     *
     * @param list<string> $filenames
     *
     * @return array<string, string|null> one description per file name
     */
    abstract protected function ownersOf(array $filenames): array;

    /**
     * The public URL of one file of this kind.
     */
    abstract protected function url(string $filename): string;
}
