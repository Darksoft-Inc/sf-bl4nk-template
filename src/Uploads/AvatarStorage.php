<?php

namespace App\Uploads;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The write path of the avatar files: the profile page replaces the
 * avatar of a signed-in account, and the maintenance page removes one
 * file together with the account that references it. Every stored name
 * matches FILENAME_PATTERN, so every avatar the pages handle is also a
 * file the kind can list and remove.
 */
final class AvatarStorage
{
    /**
     * The name rule of every avatar file: 32 lower-case hex digits and
     * the extension of a raster image, the shape replace() writes.
     */
    public const FILENAME_PATTERN = '/^[a-f0-9]{32}\.(png|jpe?g|webp|gif)$/';

    public function __construct(
        #[Autowire(param: 'app.avatar_dir')] private readonly string $avatarDir,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Stores the upload under a fresh random name, deletes the file it
     * replaces, and points the account at the new name.
     */
    public function replace(User $user, UploadedFile $file): void
    {
        $extension = $file->guessExtension();
        \assert(null !== $extension);

        $filename = bin2hex(random_bytes(16)).'.'.$extension;
        $file->move($this->avatarDir, $filename);

        $previous = $user->getAvatarFilename();
        if (null !== $previous && is_file($this->avatarDir.'/'.$previous)) {
            unlink($this->avatarDir.'/'.$previous);
        }

        $user->setAvatarFilename($filename);

        $this->entityManager->flush();
    }

    /**
     * Deletes one file and clears the account that references it. The
     * caller has validated the file name against FILENAME_PATTERN.
     */
    public function clear(string $filename): void
    {
        $owner = $this->users->findOneBy(['avatarFilename' => $filename]);
        if ($owner instanceof User) {
            $owner->setAvatarFilename(null);
        }

        unlink($this->avatarDir.'/'.$filename);

        $this->entityManager->flush();
    }
}
