<?php

namespace App\Repository;

use App\Entity\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Company>
 *
 * @method Company|null find($id, $lockMode = null, $lockVersion = null)
 * @method Company|null findOneBy(array $criteria, array $orderBy = null)
 * @method Company[]    findAll()
 * @method Company[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CompanyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Company::class);
    }

    public function save(Company $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Company $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find companies by name, ticker symbol, industry, or sector
     * Uses case-insensitive contains search logic with more flexible matching
     */
    public function findBySearchCriteria(string $searchTerm, int $limit = 25): array
    {
        // Normalize the search term
        $searchTerm = trim($searchTerm);

        // Check if it's empty after trimming
        if (empty($searchTerm)) {
            return [];
        }

        $queryBuilder = $this->createQueryBuilder('c');

        // If the search term looks like a stock symbol (all uppercase, 1-5 characters)
        if (strlen($searchTerm) <= 5 && strtoupper($searchTerm) === $searchTerm) {
            // Give higher priority to exact symbol matches
            $queryBuilder->where('c.tickerSymbol = :exactSymbol')
                ->orWhere('LOWER(c.tickerSymbol) LIKE LOWER(:partialSymbol)')
                ->orWhere('LOWER(c.name) LIKE LOWER(:nameTerm)')
                ->orWhere('LOWER(c.industry) LIKE LOWER(:searchTerm)')
                ->orWhere('LOWER(c.sector) LIKE LOWER(:searchTerm)')
                ->setParameter('exactSymbol', $searchTerm)
                ->setParameter('partialSymbol', $searchTerm . '%')
                ->setParameter('nameTerm', '%' . strtolower($searchTerm) . '%')
                ->setParameter('searchTerm', '%' . strtolower($searchTerm) . '%');
        } else {
            // Regular search with priority for name matches
            $queryBuilder->where('LOWER(c.name) LIKE LOWER(:exactName)')
                ->orWhere('LOWER(c.name) LIKE LOWER(:startName)')
                ->orWhere('LOWER(c.name) LIKE LOWER(:nameTerm)')
                ->orWhere('LOWER(c.tickerSymbol) LIKE LOWER(:symbolTerm)')
                ->orWhere('LOWER(c.industry) LIKE LOWER(:searchTerm)')
                ->orWhere('LOWER(c.sector) LIKE LOWER(:searchTerm)')
                ->setParameter('exactName', strtolower($searchTerm))
                ->setParameter('startName', strtolower($searchTerm) . '%')
                ->setParameter('nameTerm', '%' . strtolower($searchTerm) . '%')
                ->setParameter('symbolTerm', strtolower($searchTerm) . '%')
                ->setParameter('searchTerm', '%' . strtolower($searchTerm) . '%');
        }

        $queryBuilder->orderBy('c.name', 'ASC')
            ->setMaxResults($limit);

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Find companies by industry
     */
    public function findByIndustry(string $industry): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.industry = :industry')
            ->setParameter('industry', $industry)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find companies by sector
     */
    public function findBySector(string $sector): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.sector = :sector')
            ->setParameter('sector', $sector)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recently added companies
     */
    public function findRecentlyAdded(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recently updated companies
     */
    public function findRecentlyUpdated(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.updatedAt IS NOT NULL')
            ->orderBy('c.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
