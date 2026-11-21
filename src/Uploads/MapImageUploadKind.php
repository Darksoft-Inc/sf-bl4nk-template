<?php

namespace App\Uploads;

use App\Repository\MapRepository;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The pool of map background images. Files arrive through the admin upload
 * with a random name, or as pre-existing images dropped into the directory;
 * both are listed the same way. A removal is refused while any map still
 * uses the file, so a public map cannot lose its background silently.
 */
#[AutoconfigureTag('app.upload_kind')]
final class MapImageUploadKind extends AbstractDirectoryUploadKind
{
    /**
     * Uploads carry a random hex name, pre-existing files keep theirs. The
     * pattern accepts both and keeps any path fragment out: the file name
     * is always appended to the directory, never joined.
     */
    private const FILENAME_PATTERN = '/^[A-Za-z0-9_][\w.\-]*\.(png|jpe?g|webp|gif)$/i';

    public function __construct(
        #[Autowire(param: 'app.map_image_dir')] private readonly string $mapImageDir,
        private readonly MapRepository $maps,
    ) {
    }

    public function getName(): string
    {
        return 'map_image';
    }

    public function remove(string $filename): void
    {
        $path = $this->existingFile($filename);

        if ([] !== $this->maps->findByImageFilenames([$filename])) {
            throw new UploadInUseException(\sprintf('The map image "%s" is used by a map.', $filename));
        }

        unlink($path);
    }

    protected function directory(): string
    {
        return $this->mapImageDir;
    }

    protected function filenamePattern(): string
    {
        return self::FILENAME_PATTERN;
    }

    protected function label(): string
    {
        return 'map image';
    }

    /**
     * @param list<string> $filenames
     *
     * @return array<string, string|null>
     */
    protected function ownersOf(array $filenames): array
    {
        $owners = [];
        foreach ($this->maps->findByImageFilenames($filenames) as $map) {
            $owners[(string) $map->getImageFilename()][] = $map->getName();
        }

        return array_map(static fn (array $names): string => implode(', ', $names), $owners);
    }

    protected function url(string $filename): string
    {
        return '/uploads/maps/'.$filename;
    }
}
