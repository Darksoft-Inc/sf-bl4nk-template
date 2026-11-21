<?php

namespace App\Uploads;

use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('app.upload_kind')]
final class AvatarUploadKind extends AbstractDirectoryUploadKind
{
    public function __construct(
        #[Autowire(param: 'app.avatar_dir')] private readonly string $avatarDir,
        private readonly UserRepository $users,
        private readonly AvatarStorage $avatars,
    ) {
    }

    public function getName(): string
    {
        return 'avatar';
    }

    public function remove(string $filename): void
    {
        $this->existingFile($filename);

        $this->avatars->clear($filename);
    }

    protected function directory(): string
    {
        return $this->avatarDir;
    }

    protected function filenamePattern(): string
    {
        return AvatarStorage::FILENAME_PATTERN;
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
