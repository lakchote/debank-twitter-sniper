<?php

namespace App\Entity;

use App\Repository\WalletRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WalletRepository::class)]
class Wallet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    private $address;

    #[ORM\Column(type: 'string', length: 255)]
    private $name;

    #[ORM\Column(type: 'json', nullable: true)]
    private $nfts = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private $nodes = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private $buys = [];

    #[ORM\Column(type: 'boolean')]
    private $toSnipe;

    #[ORM\Column(type: 'boolean')]
    private $autoBuy;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): void
    {
        $this->address = $address;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name)
    {
        $this->name = $name;
    }

    public function getNfts(): ?array
    {
        return $this->nfts;
    }

    public function addNft(string $nft): void
    {
        $this->nfts[] = $nft;
    }

    public function getNodes(): ?array
    {
        return $this->nodes;
    }

    public function addNode(string $node): void
    {
        $this->nodes[] = $node;
    }

    public function getBuys(): ?array
    {
        return $this->buys;
    }

    public function addBuy(string $buy): void
    {
        $this->buys[] = $buy;
    }

    public function isToSnipe(): ?bool
    {
        return $this->toSnipe;
    }

    public function setToSnipe(bool $toSnipe): void
    {
        $this->toSnipe = $toSnipe;
    }

    public function isAutoBuy(): ?bool
    {
        return $this->autoBuy;
    }

    public function setAutoBuy(bool $autoBuy): void
    {
        $this->autoBuy = $autoBuy;
    }
}
