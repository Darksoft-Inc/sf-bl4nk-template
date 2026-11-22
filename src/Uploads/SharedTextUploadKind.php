<?php

namespace App\Uploads;

use App\Repository\SharedUploadRepository;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The plain-text files the members shared. The kind lists the directory
 * the way every kind does; the public URL stays unused on the
 * maintenance page, which previews images only.
 */
#[AutoconfigureTag('app.upload_kind')]
final class SharedTextUploadKind extends AbstractDirectoryUploadKind
{
    public function __construct(
        #[Autowire(param: 'app.shared_text_dir')] private readonly string $textDir,
        private readonly SharedUploadRepository $uploads,
        private readonly SharedUploadStorage $storage,
    ) {
    }

    public function getName(): string
    {
        return 'shared_text';
    }

    public function remove(string $filename): void
    {
        $this->existingFile($filename);

        $this->storage->clear($filename);
    }

    protected function directory(): string
    {
        return $this->textDir;
    }

    protected function filenamePattern(): string
    {
        return SharedUploadStorage::TEXT_FILENAME_PATTERN;
    }

    protected function label(): string
    {
        return 'shared text';
    }

    /**
     * @param list<string> $filenames
     *
     * @return array<string, string|null>
     */
    protected function ownersOf(array $filenames): array
    {
        $owners = [];
        foreach ($this->uploads->findBy(['filename' => $filenames]) as $upload) {
            $owners[$upload->getFilename()] = $upload->getOwner()?->getEmail();
        }

        return $owners;
    }

    protected function url(string $filename): string
    {
        return '/uploads/shared/text/'.$filename;
    }
}
