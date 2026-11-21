<?php

namespace App\Uploads;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('app.upload_kind')]
final class AvatarUploadKind extends AbstractDirectoryUploadKind
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

    public function remove(string $filename): void
    {
        $path = $this->existingFile($filename);

        $owner = $this->users->findOneBy(['avatarFilename' => $filename]);
        if ($owner instanceof User) {
            $owner->setAvatarFilename(null);
        }

        unlink($path);

        $this->entityManager->flush();
    }

    protected function directory(): string
    {
        return $this->avatarDir;
    }

    protected function filenamePattern(): string
    {
        return self::FILENAME_PATTERN;
    }

    protected function label(): string
    {
        return 'avatar';
    }

    /**
     * @param list<string> $filenames
     *
     * @return array<string, string|null>
     */
    protected function ownersOf(array $filenames): array
    {
        $owners = [];
        foreach ($this->users->findBy(['avatarFilename' => $filenames]) as $user) {
            $owners[(string) $user->getAvatarFilename()] = $user->getEmail();
        }

        return $owners;
    }

    protected function url(string $filename): string
    {
        return '/uploads/avatars/'.$filename;
    }
}
