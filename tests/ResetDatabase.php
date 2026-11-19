<?php

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;

trait ResetDatabase
{
    /**
     * Reset password requests reference users, so they are deleted first.
     * The order matters on databases that enforce the foreign key.
     */
    private function resetDatabase(EntityManagerInterface $entityManager): void
    {
        $entityManager->createQuery('DELETE FROM App\Entity\ResetPasswordRequest r')->execute();
        $entityManager->createQuery('DELETE FROM App\Entity\User u')->execute();
    }
}
