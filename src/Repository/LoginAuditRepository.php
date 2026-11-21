<?php

namespace App\Repository;

use App\Entity\LoginAudit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoginAudit>
 */
class LoginAuditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginAudit::class);
    }

    /**
     * Lists the audit rows newest first. The IP pattern is a SQL LIKE
     * pattern: an input without wildcards matches as a substring, an input
     * with "%" or "_" is used as typed.
     */
    public function listQueryBuilder(?string $ipPattern): QueryBuilder
    {
        $query = $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', \SortDirection::Descending)
            ->addOrderBy('a.id', \SortDirection::Descending);

        if (null !== $ipPattern && '' !== $ipPattern) {
            $containsWildcard = false !== strpbrk($ipPattern, '%_');

            $query
                ->andWhere('a.ip LIKE :ip_pattern')
                ->setParameter('ip_pattern', $containsWildcard ? $ipPattern : '%'.$ipPattern.'%');
        }

        return $query;
    }

    public function countOlderThan(\DateTimeImmutable $threshold): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.createdAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Removes every row older than the threshold and returns the count.
     */
    public function removeOlderThan(\DateTimeImmutable $threshold): int
    {
        $removed = $this->createQueryBuilder('a')
            ->delete()
            ->andWhere('a.createdAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();

        return \is_int($removed) ? $removed : 0;
    }
}
