<?php

namespace Jenssmit\AnalyticsBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Jenssmit\AnalyticsBundle\Entity\PageView;

final class PageViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageView::class);
    }

    public function save(PageView $pageView): void
    {
        $this->getEntityManager()->persist($pageView);
        $this->getEntityManager()->flush();
    }

    /**
     * @return list<PageView>
     */
    public function findSince(\DateTimeImmutable $since): array
    {
        /** @var list<PageView> $result */
        $result = $this->createQueryBuilder('p')
            ->andWhere('p.createdAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function countBefore(\DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function deleteBefore(\DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('p')
            ->delete()
            ->andWhere('p.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
