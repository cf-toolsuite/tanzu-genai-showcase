<?php

namespace App\Repository;

use App\Entity\SecFiling;
use App\Entity\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SecFiling>
 *
 * @method SecFiling|null find($id, $lockMode = null, $lockVersion = null)
 * @method SecFiling|null findOneBy(array $criteria, array $orderBy = null)
 * @method SecFiling[]    findAll()
 * @method SecFiling[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SecFilingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SecFiling::class);
    }

    /**
     * Find recent SEC filings for a company
     *
     * @param Company $company
     * @param int $limit
     * @return SecFiling[]
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
     * @return SecFiling[]
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
     * @return SecFiling[]
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

    /**
     * Find recent SEC filings for a company by form type
     *
     * @param Company $company
     * @param string|null $formType
     * @param int $limit
     * @return SecFiling[]
     */
    public function findRecentFilingsByCompany(Company $company, ?string $formType = null, int $limit = 20): array
    {
        $queryBuilder = $this->createQueryBuilder('f')
            ->andWhere('f.company = :company')
            ->setParameter('company', $company);

        if ($formType) {
            $queryBuilder
                ->andWhere('f.formType = :formType')
                ->setParameter('formType', $formType);
        }

        return $queryBuilder
            ->orderBy('f.filingDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find SEC filing by accession number
     *
     * @param string $accessionNumber
     * @return SecFiling|null
     */
    public function findByAccessionNumber(string $accessionNumber): ?SecFiling
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.accessionNumber = :accessionNumber')
            ->setParameter('accessionNumber', $accessionNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find unprocessed SEC filings
     *
     * @param int $limit
     * @return SecFiling[]
     */
    public function findUnprocessedFilings(int $limit = 10): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.isProcessed = :isProcessed')
            ->setParameter('isProcessed', false)
            ->orderBy('f.filingDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find latest 10-K filing for a company
     *
     * @param Company $company
     * @return SecFiling|null
     */
    public function findLatest10K(Company $company): ?SecFiling
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.company = :company')
            ->andWhere('f.formType = :formType')
            ->setParameter('company', $company)
            ->setParameter('formType', '10-K')
            ->orderBy('f.filingDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
