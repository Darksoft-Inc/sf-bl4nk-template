<?php

namespace App\Uploads;

use App\Repository\SharedUploadRepository;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The images the members shared. Every file has one row, so the owner
 * always resolves, and a removal takes the row with the file.
 */
#[AutoconfigureTag('app.upload_kind')]
final class SharedImageUploadKind extends AbstractDirectoryUploadKind
{
    public function __construct(
        #[Autowire(param: 'app.shared_image_dir')] private readonly string $imageDir,
        private readonly SharedUploadRepository $uploads,
        private readonly SharedUploadStorage $storage,
    ) {
    }

    public function getName(): string
    {
        return 'shared_image';
    }

    public function remove(string $filename): void
    {
        $this->existingFile($filename);

        $this->storage->clear($filename);
    }

    protected function directory(): string
    {
        return $this->imageDir;
    }

    protected function filenamePattern(): string
    {
        return SharedUploadStorage::IMAGE_FILENAME_PATTERN;
    }

    protected function label(): string
    {
        return 'shared image';
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
        return '/uploads/shared/images/'.$filename;
    }
}
