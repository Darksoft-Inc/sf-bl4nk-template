<?php

namespace App\Repository;

use App\Entity\Map;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Map>
 */
class MapRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Map::class);
    }

    /**
     * @return list<Map>
     */
    public function findLatestFirst(): array
    {
        return $this->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    /**
     * @param list<string> $filenames
     *
     * @return list<Map>
     */
    public function findByImageFilenames(array $filenames): array
    {
        return $this->findBy(['imageFilename' => $filenames], ['id' => 'ASC']);
    }
}
