<?php

namespace App\Repository;

use App\Entity\InstitutionalOwnership;
use App\Entity\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InstitutionalOwnership>
 *
 * @method InstitutionalOwnership|null find($id, $lockMode = null, $lockVersion = null)
 * @method InstitutionalOwnership|null findOneBy(array $criteria, array $orderBy = null)
 * @method InstitutionalOwnership[]    findAll()
 * @method InstitutionalOwnership[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InstitutionalOwnershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InstitutionalOwnership::class);
    }

    /**
     * Find top institutional holders for a company
     *
     * @param Company $company
     * @param int $limit
     * @return InstitutionalOwnership[]
     */
    public function findTopHolders(Company $company, int $limit = 10): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.company = :company')
            ->setParameter('company', $company)
            ->orderBy('i.shares', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find institutional holders with significant changes in position
     *
     * @param Company $company
     * @param float $changeThreshold Percentage change threshold (e.g., 5.0 for 5%)
     * @param int $limit
     * @return InstitutionalOwnership[]
     */
    public function findSignificantChanges(Company $company, float $changeThreshold = 5.0, int $limit = 10): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.company = :company')
            ->andWhere('ABS(i.percentageChange) >= :threshold')
            ->setParameter('company', $company)
            ->setParameter('threshold', $changeThreshold)
            ->orderBy('ABS(i.percentageChange)', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find institutional holders by name (partial match)
     *
     * @param Company $company
     * @param string $institutionName
     * @return InstitutionalOwnership[]
     */
    public function findByInstitutionName(Company $company, string $institutionName): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.company = :company')
            ->andWhere('i.institutionName LIKE :name')
            ->setParameter('company', $company)
            ->setParameter('name', '%' . $institutionName . '%')
            ->orderBy('i.shares', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculate total institutional ownership percentage for a company
     *
     * @param Company $company
     * @return float
     */
    public function calculateTotalOwnershipPercentage(Company $company): float
    {
        $result = $this->createQueryBuilder('i')
            ->select('SUM(i.percentageOwned) as totalPercentage')
            ->andWhere('i.company = :company')
            ->setParameter('company', $company)
            ->getQuery()
            ->getSingleScalarResult();

        return (float)$result;
    }
}
