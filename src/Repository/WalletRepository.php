<?php

namespace App\Repository;

use App\Entity\Wallet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Wallet|null find($id, $lockMode = null, $lockVersion = null)
 * @method Wallet|null findOneBy(array $criteria, array $orderBy = null)
 * @method Wallet[]    findAll()
 * @method Wallet[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WalletRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Wallet::class);
    }

    public function save(Wallet $wallet)
    {
        $this->_em->persist($wallet);
        $this->_em->flush();
    }

    public function persist(Wallet $wallet)
    {
        $this->_em->persist($wallet);
    }

    public function flush()
    {
        $this->_em->flush();
    }

    public function findAllToSnipe(): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.toSnipe = :val')
            ->setParameter('val', true)
            ->orderBy('w.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
