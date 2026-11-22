<?php

namespace App\Uploads;

use App\Entity\SharedUpload;
use App\Entity\SharedUploadType;
use App\Entity\User;
use App\Reference\Reference;
use App\Reference\ReferenceDetail;
use App\Repository\SharedUploadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The write path of the shared files: the members' section stores and
 * removes them, and the maintenance kinds clear a file together with
 * its row. Every stored name matches the pattern of its kind, so every
 * file the pages handle is also a file the kinds can list and remove.
 */
#[Reference(
    slug: 'shared-files',
    title: 'Shared files',
    summary: 'Every member shares an image or a plain-text file with the others. '
        .'The list shows the newest first and only the uploader removes a file.',
    group: 'Files',
    details: [
        new ReferenceDetail('Accepted content', 'PNG, JPEG, WebP, GIF images or plain-text files, checked by content, not by name.'),
        new ReferenceDetail('Size limit', 'At most '.self::MAX_SIZE.' per file.'),
        new ReferenceDetail('Category', 'The sniffed content decides: a PNG named "notes.txt" is an image, and plain text always lands under a .txt name.'),
        new ReferenceDetail('Removal', 'The uploader removes a file from its page, and the maintenance pages remove any file.'),
    ],
)]
final class SharedUploadStorage
{
    /**
     * The size limit every shared file obeys, enforced by the upload
     * form and cited by the reference page.
     */
    public const MAX_SIZE = '2M';

    /**
     * The name rule of every stored image: 32 lower-case hex digits and
     * the extension of a raster image, the shape store() writes.
     */
    public const IMAGE_FILENAME_PATTERN = '/^[a-f0-9]{32}\.(png|jpe?g|webp|gif)$/';

    /**
     * The name rule of every stored text file: plain text always lands
     * under a .txt name, so a served file is always text/plain.
     */
    public const TEXT_FILENAME_PATTERN = '/^[a-f0-9]{32}\.txt$/';

    public function __construct(
        #[Autowire(param: 'app.shared_image_dir')] private readonly string $imageDir,
        #[Autowire(param: 'app.shared_text_dir')] private readonly string $textDir,
        private readonly SharedUploadRepository $uploads,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Stores the upload under a fresh random name and records the row.
     * The sniffed extension decides the category, not the original
     * name: a PNG named "notes.txt" is an image.
     *
     * @throws \InvalidArgumentException when the content suggests neither a supported image nor plain text
     */
    public function store(User $owner, UploadedFile $file, \DateTimeImmutable $uploadedAt): SharedUpload
    {
        $extension = $file->guessExtension();
        \assert(null !== $extension);

        $type = match ($extension) {
            'png', 'jpeg', 'webp', 'gif' => SharedUploadType::Image,
            'txt' => SharedUploadType::Text,
            default => throw new \InvalidArgumentException(\sprintf('"%s" is not a supported shared file type.', $extension)),
        };

        $filename = bin2hex(random_bytes(16)).'.'.$extension;
        $file->move($this->directory($type), $filename);

        $originalName = $file->getClientOriginalName();
        $upload = new SharedUpload(
            $owner,
            $type,
            $filename,
            '' !== $originalName ? mb_substr($originalName, 0, 255) : 'upload.'.$extension,
            (int) filesize($this->directory($type).'/'.$filename),
            $uploadedAt,
        );

        $this->entityManager->persist($upload);
        $this->entityManager->flush();

        return $upload;
    }

    /**
     * Deletes one file and its row. The caller has checked that the
     * signed-in member owns the file.
     */
    public function remove(SharedUpload $upload): void
    {
        unlink($this->directory($upload->getType()).'/'.$upload->getFilename());

        $this->entityManager->remove($upload);
        $this->entityManager->flush();
    }

    /**
     * Deletes one file and the row that references it, used by the
     * maintenance kinds. The caller has validated the file name against
     * the kind pattern. A file without a row, an orphan of a failed
     * write, still goes away.
     */
    public function clear(string $filename): void
    {
        $upload = $this->uploads->findOneBy(['filename' => $filename]);

        if ($upload instanceof SharedUpload) {
            $this->entityManager->remove($upload);
        }

        unlink($this->directory($this->typeOf($filename)).'/'.$filename);

        $this->entityManager->flush();
    }

    /**
     * The stored text of a plain-text upload, for the detail page.
     */
    public function readText(SharedUpload $upload): string
    {
        $content = file_get_contents($this->directory($upload->getType()).'/'.$upload->getFilename());
        \assert(false !== $content);

        return $content;
    }

    private function typeOf(string $filename): SharedUploadType
    {
        return str_ends_with($filename, '.txt') ? SharedUploadType::Text : SharedUploadType::Image;
    }

    private function directory(SharedUploadType $type): string
    {
        return SharedUploadType::Image === $type ? $this->imageDir : $this->textDir;
    }
}
