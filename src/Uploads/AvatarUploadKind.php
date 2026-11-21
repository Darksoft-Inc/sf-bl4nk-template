<?php

namespace App\Uploads;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('app.upload_kind')]
final class AvatarUploadKind implements UploadKindInterface
{
    private const FILENAME_PATTERN = '/^[a-f0-9]{32}\.(png|jpe?g|webp|gif)$/';

    public function __construct(
        #[Autowire(param: 'app.avatar_dir')] private readonly string $avatarDir,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getName(): string
    {
        return 'avatar';
    }

    public function list(): array
    {
        if (!is_dir($this->avatarDir)) {
            return [];
        }

        $names = scandir($this->avatarDir);
        \assert(\is_array($names));

        $files = [];
        foreach ($names as $filename) {
            if (str_starts_with($filename, '.')) {
                continue;
            }

            $path = $this->avatarDir.'/'.$filename;

            $modifiedAt = filemtime($path);
            $size = filesize($path);
            \assert(false !== $modifiedAt && false !== $size);

            $owner = $this->users->findOneBy(['avatarFilename' => $filename]);

            $files[] = new UploadedFile(
                $this->getName(),
                $filename,
                $owner instanceof User ? $owner->getEmail() : null,
                '/uploads/avatars/'.$filename,
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
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid avatar file name.', $filename));
        }

        $path = $this->avatarDir.'/'.$filename;

        if (!is_file($path)) {
            throw new \InvalidArgumentException(\sprintf('The avatar file "%s" does not exist.', $filename));
        }

        $owner = $this->users->findOneBy(['avatarFilename' => $filename]);
        if ($owner instanceof User) {
            $owner->setAvatarFilename(null);
        }

        unlink($path);

        $this->entityManager->flush();
    }
}
