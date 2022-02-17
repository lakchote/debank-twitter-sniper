<?php

namespace App\Entity;

use App\Repository\TwitterInfluencerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TwitterInfluencerRepository::class)]
class TwitterInfluencer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    private $username;

    #[ORM\Column(type: 'json', nullable: true)]
    private $following = [];

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $userId;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username)
    {
        $this->username = $username;
    }

    public function getFollowing(): ?array
    {
        return $this->following;
    }

    public function addFollowing(string $username)
    {
        $this->following[] = $username;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(string $userId)
    {
        $this->userId = $userId;
    }
}
