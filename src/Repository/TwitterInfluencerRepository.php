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

    public function save(TwitterInfluencer $wallet)
    {
        $this->_em->persist($wallet);
        $this->_em->flush();
    }

    public function persist(TwitterInfluencer $wallet)
    {
        $this->_em->persist($wallet);
    }

    public function flush()
    {
        $this->_em->flush();
    }
}
