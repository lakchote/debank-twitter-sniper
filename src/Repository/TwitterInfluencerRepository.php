<?php

namespace App\Repository;

use App\Entity\TwitterInfluencer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TwitterInfluencerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TwitterInfluencer::class);
    }

    public function deleteUsersNotInArray(array $usersToKeep): void
    {
        $qb = $this->createQueryBuilder('t');
        $qb->delete()
            ->where($qb->expr()->notIn('t.username', $usersToKeep))
            ->getQuery()
            ->execute();
    }

    public function deleteUsersInArray(array $userToDelete): void
    {
        $qb = $this->createQueryBuilder('t');
        $qb->delete()
            ->where($qb->expr()->in('t.username', $userToDelete))
            ->getQuery()
            ->execute();
    }

    public function countFollowingsWithUsername(string $username): int
    {
        return $this->createQueryBuilder('t')
            ->select('count(t)')
            ->where('t.following LIKE :username')
            ->setParameter('username', '%'.$username.'%')
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function findAllTwitterInfluencerNames(): array
    {
        return $this->createQueryBuilder('t')
            ->select('t.username')
            ->getQuery()
            ->getResult();
    }

    public function persist(TwitterInfluencer $wallet): void
    {
        $this->_em->persist($wallet);
    }

    public function flush(): void
    {
        $this->_em->flush();
    }
}
