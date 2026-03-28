<?php

namespace App\Repository;

use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * Returns a Paginator for the events list with optional search/location filter.
     * fetchJoinCollection=false avoids the DISTINCT issue with joined collections.
     */
    public function findPaginatedWithSearch(int $page, int $limit, ?string $search, ?string $location): Paginator
    {
        $qb = $this->createQueryBuilder('e')
            ->orderBy('e.date', 'ASC');

        if ($search !== null && $search !== '') {
            $qb->andWhere('e.title LIKE :search OR e.description LIKE :search OR e.location LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($location !== null && $location !== '') {
            $qb->andWhere('e.location LIKE :location')
               ->setParameter('location', '%' . $location . '%');
        }

        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        // fetchJoinCollection=false: we are not joining a collection, so no DISTINCT needed
        return new Paginator($qb, fetchJoinCollection: false);
    }

    public function findUpcoming(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.date >= :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('e.date', 'ASC')
            ->setMaxResults(6)
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
