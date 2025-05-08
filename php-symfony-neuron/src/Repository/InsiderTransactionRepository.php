<?php

namespace App\Repository;

use App\Entity\InsiderTransaction;
use App\Entity\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InsiderTransaction>
 *
 * @method InsiderTransaction|null find($id, $lockMode = null, $lockVersion = null)
 * @method InsiderTransaction|null findOneBy(array $criteria, array $orderBy = null)
 * @method InsiderTransaction[]    findAll()
 * @method InsiderTransaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InsiderTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InsiderTransaction::class);
    }

    /**
     * Find recent insider transactions for a company
     *
     * @param Company $company
     * @param int $limit
     * @return InsiderTransaction[]
     */
    public function findRecentForCompany(Company $company, int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.company = :company')
            ->setParameter('company', $company)
            ->orderBy('t.transactionDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find insider transactions by transaction type
     *
     * @param Company $company
     * @param string $transactionType
     * @param int $limit
     * @return InsiderTransaction[]
     */
    public function findByCompanyAndType(Company $company, string $transactionType, int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.company = :company')
            ->andWhere('t.transactionType = :type')
            ->setParameter('company', $company)
            ->setParameter('type', $transactionType)
            ->orderBy('t.transactionDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find insider transactions by insider name
     *
     * @param Company $company
     * @param string $insiderName
     * @param int $limit
     * @return InsiderTransaction[]
     */
    public function findByCompanyAndInsider(Company $company, string $insiderName, int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.company = :company')
            ->andWhere('t.insiderName = :name')
            ->setParameter('company', $company)
            ->setParameter('name', $insiderName)
            ->orderBy('t.transactionDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find large insider transactions (by value)
     *
     * @param Company $company
     * @param float $minValue
     * @param int $limit
     * @return InsiderTransaction[]
     */
    public function findLargeTransactions(Company $company, float $minValue = 100000, int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.company = :company')
            ->andWhere('t.value >= :minValue')
            ->setParameter('company', $company)
            ->setParameter('minValue', $minValue)
            ->orderBy('t.value', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
