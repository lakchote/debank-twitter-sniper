<?php

namespace App\Entity;

use App\Repository\WalletRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    #[ORM\Column(type: 'json', nullable: true)]
    private $stakes = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private $unstakes = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private $swaps = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private $contracts = [];

    #[ORM\Column(type: 'boolean')]
    private $toSnipe;

    #[ORM\Column(type: 'boolean')]
    private $autoBuy;

    #[ORM\OneToMany(mappedBy: 'wallet', targetEntity: Transaction::class)]
    private $transactions;

    public function __construct()
    {
        $this->transactions = new ArrayCollection();
    }

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

    public function getStakes(): ?array
    {
        return $this->stakes;
    }

    public function addStake(string $stake): void
    {
        $this->stakes[] = $stake;
    }

    public function getUnstakes(): ?array
    {
        return $this->unstakes;
    }

    public function addUnstake(string $unstake): void
    {
        $this->unstakes[] = $unstake;
    }

    public function getSwaps(): ?array
    {
        return $this->swaps;
    }

    public function addSwap(string $swap): void
    {
        $this->swaps[] = $swap;
    }

    public function getContracts(): ?array
    {
        return $this->contracts;
    }

    public function addContract(string $contract): void
    {
        $this->contracts[] = $contract;
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

    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function addTransaction(Transaction $transaction): self
    {
        if (!$this->transactions->contains($transaction)) {
            $this->transactions[] = $transaction;
            $transaction->setWallet($this);
        }

        return $this;
    }

    public function removeTransaction(Transaction $transaction): self
    {
        if ($this->transactions->removeElement($transaction)) {
            // set the owning side to null (unless already changed)
            if ($transaction->getWallet() === $this) {
                $transaction->setWallet(null);
            }
        }

        return $this;
    }
}
