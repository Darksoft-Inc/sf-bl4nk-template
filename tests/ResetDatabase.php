<?php

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;

trait ResetDatabase
{
    /**
     * Dependent rows are deleted before the users: announcements will carry
     * an author reference, and reset password requests reference users. The
     * order matters on databases that enforce the foreign key.
     */
    private function resetDatabase(EntityManagerInterface $entityManager): void
    {
        $entityManager->createQuery('DELETE FROM App\Entity\Announcement a')->execute();
        $entityManager->createQuery('DELETE FROM App\Entity\ResetPasswordRequest r')->execute();
        $entityManager->createQuery('DELETE FROM App\Entity\User u')->execute();
    }
}
