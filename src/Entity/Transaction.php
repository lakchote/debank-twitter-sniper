<?php

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: '`transaction`')]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    private $type;

    #[ORM\Column(type: 'datetime')]
    private $date;

    #[ORM\Column(type: 'string', length: 255)]
    private $token;

    #[ORM\ManyToOne(targetEntity: Wallet::class, inversedBy: 'transactions')]
    private $wallet;

    #[ORM\Column(type: 'string', length: 255)]
    private $txUrl;

    #[ORM\Column(type: 'string', length:255, nullable: true)]
    private $walletNetWorth;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type)
    {
        $this->type = $type;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date)
    {
        $this->date = $date;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token)
    {
        $this->token = $token;
    }

    public function getWallet(): ?Wallet
    {
        return $this->wallet;
    }

    public function setWallet(?Wallet $wallet)
    {
        $this->wallet = $wallet;
    }

    public function getTxUrl(): string
    {
        return $this->txUrl;
    }

    public function setTxUrl(string $txUrl)
    {
        $this->txUrl = $txUrl;
    }

    public function getWalletNetWorth(): ?string
    {
        return $this->walletNetWorth;
    }

    public function setWalletNetWorth(string $walletNetWorth): void
    {
        $this->walletNetWorth = $walletNetWorth;
    }
}
