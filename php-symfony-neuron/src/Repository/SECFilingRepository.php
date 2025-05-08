<?php

namespace App\Repository;

use App\Entity\SECFiling;
use App\Entity\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SECFiling>
 *
 * @method SECFiling|null find($id, $lockMode = null, $lockVersion = null)
 * @method SECFiling|null findOneBy(array $criteria, array $orderBy = null)
 * @method SECFiling[]    findAll()
 * @method SECFiling[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SECFilingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SECFiling::class);
    }

    /**
     * Find recent SEC filings for a company
     *
     * @param Company $company
     * @param int $limit
     * @return SECFiling[]
     */
    public function findRecentForCompany(Company $company, int $limit = 10): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.company = :company')
            ->setParameter('company', $company)
            ->orderBy('f.filingDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find SEC filings for a company by type
     *
     * @param Company $company
     * @param string $type
     * @param int $limit
     * @return SECFiling[]
     */
    public function findByCompanyAndType(Company $company, string $type, int $limit = 10): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.company = :company')
            ->andWhere('f.type = :type')
            ->setParameter('company', $company)
            ->setParameter('type', $type)
            ->orderBy('f.filingDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find SEC filings for a company within a date range
     *
     * @param Company $company
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @return SECFiling[]
     */
    public function findForCompanyInDateRange(Company $company, \DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.company = :company')
            ->andWhere('f.filingDate >= :startDate')
            ->andWhere('f.filingDate <= :endDate')
            ->setParameter('company', $company)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('f.filingDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
