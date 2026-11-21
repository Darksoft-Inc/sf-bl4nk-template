<?php

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;

trait ResetDatabase
{
    /**
     * Dependent rows are deleted before the users: announcements will carry
     * an author reference, reset password requests and login audit rows
     * reference users. Map rows reference only each other, but each test
     * starts with clean map tables as well. The order matters on databases
     * that enforce the foreign key.
     */
    private function resetDatabase(EntityManagerInterface $entityManager): void
    {
        $entityManager->createQuery('DELETE FROM App\Entity\Announcement a')->execute();
        $entityManager->createQuery('DELETE FROM App\Entity\ResetPasswordRequest r')->execute();
        $entityManager->createQuery('DELETE FROM App\Entity\LoginAudit a')->execute();
        $entityManager->createQuery('DELETE FROM App\Entity\MaintenanceRun r')->execute();
        $entityManager->createQuery('DELETE FROM App\Entity\MapPoint p')->execute();
        $entityManager->createQuery('DELETE FROM App\Entity\MapRoute r')->execute();
        $entityManager->createQuery('DELETE FROM App\Entity\MapRouteType t')->execute();
        $entityManager->createQuery('DELETE FROM App\Entity\Map m')->execute();
        $entityManager->createQuery('DELETE FROM App\Entity\User u')->execute();
    }
}
