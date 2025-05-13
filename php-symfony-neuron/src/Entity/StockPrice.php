<?php

namespace App\Entity;

use App\Repository\StockPriceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockPriceRepository::class)]
#[ORM\Index(columns: ["company_id", "date"], name: "idx_stock_price_company_date")]
class StockPrice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'stockPrices', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Company $company = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column]
    private float $open = 0.0;

    #[ORM\Column]
    private float $high = 0.0;

    #[ORM\Column]
    private float $low = 0.0;

    #[ORM\Column]
    private float $close = 0.0;

    #[ORM\Column]
    private float $adjustedClose = 0.0;

    #[ORM\Column]
    private int $volume = 0;

    #[ORM\Column(nullable: true)]
    private ?float $change = null;

    #[ORM\Column(nullable: true)]
    private ?float $changePercent = null;

    #[ORM\Column(length: 10)]
    private ?string $period = 'daily';

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $source = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getOpen(): float
    {
        return $this->open;
    }

    public function setOpen(float $open): static
    {
        $this->open = $open;

        return $this;
    }

    public function getHigh(): float
    {
        return $this->high;
    }

    public function setHigh(float $high): static
    {
        $this->high = $high;

        return $this;
    }

    public function getLow(): float
    {
        return $this->low;
    }

    public function setLow(float $low): static
    {
        $this->low = $low;

        return $this;
    }

    public function getClose(): float
    {
        return $this->close;
    }

    public function setClose(float $close): static
    {
        $this->close = $close;

        return $this;
    }

    public function getAdjustedClose(): float
    {
        return $this->adjustedClose;
    }

    public function setAdjustedClose(float $adjustedClose): static
    {
        $this->adjustedClose = $adjustedClose;

        return $this;
    }

    public function getVolume(): int
    {
        return $this->volume;
    }

    public function setVolume(int $volume): static
    {
        $this->volume = $volume;

        return $this;
    }

    public function getChange(): ?float
    {
        return $this->change;
    }

    public function setChange(?float $change): static
    {
        $this->change = $change;

        return $this;
    }

    public function getChangePercent(): ?float
    {
        return $this->changePercent;
    }

    public function setChangePercent(?float $changePercent): static
    {
        $this->changePercent = $changePercent;

        return $this;
    }

    public function getPeriod(): ?string
    {
        return $this->period;
    }

    public function setPeriod(string $period): static
    {
        $this->period = $period;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): static
    {
        $this->source = $source;

        return $this;
    }
}
