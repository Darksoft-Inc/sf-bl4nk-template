<?php

namespace App\Dataset\Type;

use App\Dataset\DatasetRecord;
use App\Dataset\DatasetResolver;
use App\Dataset\DatasetTypeInterface;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * The "users" section. The "password" field holds a plain password, hashed
 * at load time; naming it on an existing record resets the password.
 */
#[AutoconfigureTag('app.dataset_type')]
final class UserDatasetType implements DatasetTypeInterface
{
    private const FIELDS = ['email', 'password', 'roles', 'isVerified'];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function getName(): string
    {
        return 'users';
    }

    public function getEntityClass(): string
    {
        return User::class;
    }

    public function getFieldNames(): array
    {
        return self::FIELDS;
    }

    public function getRequiredFieldNames(): array
    {
        return ['email', 'password'];
    }

    public function create(DatasetRecord $record, DatasetResolver $resolver): object
    {
        $user = new User();
        $user->setDatasetSlug($record->slug);
        $this->apply($user, $record);

        return $user;
    }

    public function update(object $entity, DatasetRecord $record, DatasetResolver $resolver): void
    {
        \assert($entity instanceof User);

        $this->apply($entity, $record);
    }

    private function apply(User $user, DatasetRecord $record): void
    {
        if ($record->has('email')) {
            $user->setEmail($record->string('email'));
        }

        if ($record->has('password')) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $record->string('password')));
        }

        if ($record->has('roles')) {
            $user->setRoles($record->strings('roles'));
        }

        if ($record->has('isVerified')) {
            $user->setIsVerified($record->boolean('isVerified'));
        }
    }
}
