<?php

namespace App\Uploads;

use App\Entity\Map;
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
final class MapImageUploadKind implements UploadKindInterface
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

    public function list(): array
    {
        if (!is_dir($this->mapImageDir)) {
            return [];
        }

        $names = scandir($this->mapImageDir);
        \assert(\is_array($names));

        $files = [];
        foreach ($names as $filename) {
            if (str_starts_with($filename, '.')) {
                continue;
            }

            $path = $this->mapImageDir.'/'.$filename;

            $modifiedAt = filemtime($path);
            $size = filesize($path);
            \assert(false !== $modifiedAt && false !== $size);

            $owners = array_map(static fn (Map $map): string => $map->getName(), $this->maps->findByImageFilename($filename));

            $files[] = new UploadedFile(
                $this->getName(),
                $filename,
                [] === $owners ? null : implode(', ', $owners),
                '/uploads/maps/'.$filename,
                $size,
                (new \DateTimeImmutable())->setTimestamp($modifiedAt),
            );
        }

        usort($files, static fn (UploadedFile $a, UploadedFile $b): int => strcmp($a->filename, $b->filename));

        return $files;
    }

    public function remove(string $filename): void
    {
        if (!preg_match(self::FILENAME_PATTERN, $filename)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid map image file name.', $filename));
        }

        $path = $this->mapImageDir.'/'.$filename;

        if (!is_file($path)) {
            throw new \InvalidArgumentException(\sprintf('The map image "%s" does not exist.', $filename));
        }

        if ([] !== $this->maps->findByImageFilename($filename)) {
            throw new UploadInUseException(\sprintf('The map image "%s" is used by a map.', $filename));
        }

        unlink($path);
    }
}
