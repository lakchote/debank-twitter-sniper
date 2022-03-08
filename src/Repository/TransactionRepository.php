<?php

namespace App\Repository;

use App\Entity\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    public function save(Transaction $transaction)
    {
        $this->_em->persist($transaction);
        $this->_em->flush();
    }

    public function persist(Transaction $transaction)
    {
        $this->_em->persist($transaction);
    }

    public function flush()
    {
        $this->_em->flush();
    }
}
