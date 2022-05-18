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

    public function hasWalletAlreadyBoughtToken(string $token, int $walletId, string $txUrl): bool
    {
        $qb = $this->createQueryBuilder('t');
        $qb->select('COUNT(t.id)');
        $qb->where('t.wallet = :walletId');
        $qb->andWhere('t.token = :token');
        $qb->andWhere('t.txUrl != :txUrl');
        $qb->setParameters([
            'walletId' => $walletId,
            'token' => $token,
            'txUrl' => $txUrl,
        ]);
        $result = $qb->getQuery()->getSingleScalarResult();

        return $result > 0;
    }

    public function hasOtherWalletsAlreadyBoughtToken(string $token, int $walletId): bool
    {
        $qb = $this->createQueryBuilder('t');
        $qb->select('COUNT(t.id)');
        $qb->where('t.wallet != :walletId');
        $qb->andWhere('t.token = :token');
        $qb->setParameters([
            'walletId' => $walletId,
            'token' => $token,
        ]);
        $result = $qb->getQuery()->getSingleScalarResult();

        return $result > 0;
    }

    public function save(Transaction $transaction)
    {
        $this->_em->persist($transaction);
        $this->_em->flush();
    }

    public function persist(Transaction $transaction): void
    {
        $this->_em->persist($transaction);
    }

    public function flush(): void
    {
        $this->_em->flush();
    }
}
