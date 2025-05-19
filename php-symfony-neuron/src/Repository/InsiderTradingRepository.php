<?php

namespace App\Repository;

use App\Entity\InsiderTrading;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method InsiderTrading|null find($id, $lockMode = null, $lockVersion = null)
 * @method InsiderTrading|null findOneBy(array $criteria, array $orderBy = null)
 * @method InsiderTrading[]    findAll()
 * @method InsiderTrading[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InsiderTradingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InsiderTrading::class);
    }

    /**
     * Find insider trades by stock symbol
     */
    public function findBySymbol(string $symbol, int $limit = 20): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.symbol = :symbol')
            ->setParameter('symbol', $symbol)
            ->orderBy('i.transactionDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find trades by insider position (like CEO, CFO, etc)
     */
    public function findByPosition(string $symbol, string $position, int $limit = 20): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.symbol = :symbol')
            ->andWhere('i.position LIKE :position')
            ->setParameter('symbol', $symbol)
            ->setParameter('position', '%' . $position . '%')
            ->orderBy('i.transactionDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent large transactions by value
     */
    public function findLargeTransactions(string $symbol, float $minValue = 1000000, int $limit = 10): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.symbol = :symbol')
            ->andWhere('i.transactionValue >= :minValue')
            ->setParameter('symbol', $symbol)
            ->setParameter('minValue', $minValue)
            ->orderBy('i.transactionValue', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get net buying/selling by transaction type
     */
    public function getNetTransactionsByType(string $symbol, ?\DateTime $startDate = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->select('i.transactionType, SUM(i.transactionValue) as totalValue, SUM(i.shares) as totalShares')
            ->andWhere('i.symbol = :symbol')
            ->setParameter('symbol', $symbol)
            ->groupBy('i.transactionType');

        if ($startDate) {
            $qb->andWhere('i.transactionDate >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find trades within a date range
     */
    public function findByDateRange(string $symbol, \DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.symbol = :symbol')
            ->andWhere('i.transactionDate >= :startDate')
            ->andWhere('i.transactionDate <= :endDate')
            ->setParameter('symbol', $symbol)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('i.transactionDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
