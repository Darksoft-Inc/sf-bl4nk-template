<?php

namespace App\Repository;

use App\Entity\MaintenanceRun;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MaintenanceRun>
 */
class MaintenanceRunRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MaintenanceRun::class);
    }

    /**
     * @return list<MaintenanceRun>
     */
    public function findLatestByTask(string $task, int $limit): array
    {
        return $this->findBy(
            ['task' => $task],
            ['ranAt' => 'DESC', 'id' => 'DESC'],
            $limit,
        );
    }
}
