<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Sets a new password on an account and writes it. Registration, the
 * password reset, and the profile page share this one write path, so a
 * future rule that must hold for every new password (a notification, a
 * session reset) lands here once and applies to the three flows.
 */
final class PasswordChanger
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * The persist covers the registration flow, where the account is new;
     * on a managed account it changes nothing.
     */
    public function change(User $user, string $plainPassword): void
    {
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
