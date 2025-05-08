<?php

namespace App\Repository;

use App\Entity\AnalystRating;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AnalystRating>
 *
 * @method AnalystRating|null find($id, $lockMode = null, $lockVersion = null)
 * @method AnalystRating|null findOneBy(array $criteria, array $orderBy = null)
 * @method AnalystRating[]    findAll()
 * @method AnalystRating[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AnalystRatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalystRating::class);
    }

    public function save(AnalystRating $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AnalystRating $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find latest analyst ratings for a company
     */
    public function findLatestByCompany($company, $limit = 10)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.company = :company')
            ->setParameter('company', $company)
            ->orderBy('a.ratingDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
