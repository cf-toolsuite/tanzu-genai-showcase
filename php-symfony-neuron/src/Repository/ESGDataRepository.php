<?php

namespace App\Repository;

use App\Entity\ESGData;
use App\Entity\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ESGData>
 *
 * @method ESGData|null find($id, $lockMode = null, $lockVersion = null)
 * @method ESGData|null findOneBy(array $criteria, array $orderBy = null)
 * @method ESGData[]    findAll()
 * @method ESGData[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ESGDataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ESGData::class);
    }

    /**
     * Find the most recent ESG data for a company
     *
     * @param Company $company
     * @return ESGData|null
     */
    public function findLatestForCompany(Company $company): ?ESGData
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.company = :company')
            ->setParameter('company', $company)
            ->orderBy('e.lastUpdated', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find ESG data for a company within a date range
     *
     * @param Company $company
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @return ESGData[]
     */
    public function findForCompanyInDateRange(Company $company, \DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.company = :company')
            ->andWhere('e.lastUpdated >= :startDate')
            ->andWhere('e.lastUpdated <= :endDate')
            ->setParameter('company', $company)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('e.lastUpdated', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
