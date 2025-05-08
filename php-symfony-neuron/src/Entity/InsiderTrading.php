<?php

namespace App\Entity;

use App\Repository\InsiderTradingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InsiderTradingRepository::class)]
#[ORM\Table(name: 'insider_trading')]
class InsiderTrading
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    private ?string $symbol = null;

    #[ORM\Column(length: 100)]
    private ?string $insiderName = null;

    #[ORM\Column(length: 100)]
    private ?string $position = null;

    #[ORM\Column(length: 30)]
    private ?string $transactionType = null;

    #[ORM\Column(type: 'integer')]
    private ?int $shares = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $sharePrice = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $transactionValue = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $postTransactionShares = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $transactionDate = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $reportedDate = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSymbol(): ?string
    {
        return $this->symbol;
    }

    public function setSymbol(string $symbol): self
    {
        $this->symbol = $symbol;
        return $this;
    }

    public function getInsiderName(): ?string
    {
        return $this->insiderName;
    }

    public function setInsiderName(string $insiderName): self
    {
        $this->insiderName = $insiderName;
        return $this;
    }

    public function getPosition(): ?string
    {
        return $this->position;
    }

    public function setPosition(string $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function getTransactionType(): ?string
    {
        return $this->transactionType;
    }

    public function setTransactionType(string $transactionType): self
    {
        $this->transactionType = $transactionType;
        return $this;
    }

    public function getShares(): ?int
    {
        return $this->shares;
    }

    public function setShares(int $shares): self
    {
        $this->shares = $shares;
        return $this;
    }

    public function getSharePrice(): ?float
    {
        return $this->sharePrice;
    }

    public function setSharePrice(?float $sharePrice): self
    {
        $this->sharePrice = $sharePrice;
        return $this;
    }

    public function getTransactionValue(): ?float
    {
        return $this->transactionValue;
    }

    public function setTransactionValue(?float $transactionValue): self
    {
        $this->transactionValue = $transactionValue;
        return $this;
    }

    public function getPostTransactionShares(): ?int
    {
        return $this->postTransactionShares;
    }

    public function setPostTransactionShares(?int $postTransactionShares): self
    {
        $this->postTransactionShares = $postTransactionShares;
        return $this;
    }

    public function getTransactionDate(): ?\DateTimeInterface
    {
        return $this->transactionDate;
    }

    public function setTransactionDate(\DateTimeInterface $transactionDate): self
    {
        $this->transactionDate = $transactionDate;
        return $this;
    }

    public function getReportedDate(): ?\DateTimeInterface
    {
        return $this->reportedDate;
    }

    public function setReportedDate(?\DateTimeInterface $reportedDate): self
    {
        $this->reportedDate = $reportedDate;
        return $this;
    }
}
