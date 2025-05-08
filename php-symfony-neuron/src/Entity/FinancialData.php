<?php

namespace App\Entity;

use App\Repository\FinancialDataRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FinancialDataRepository::class)]
class FinancialData
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'financialData')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Company $company = null;

    #[ORM\Column(length: 20)]
    private ?string $reportType = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $fiscalQuarter = null;

    #[ORM\Column(nullable: true)]
    private ?int $fiscalYear = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $reportDate = null;

    #[ORM\Column(nullable: true)]
    private ?float $revenue = null;

    #[ORM\Column(nullable: true)]
    private ?float $netIncome = null;

    #[ORM\Column(nullable: true)]
    private ?float $eps = null;

    #[ORM\Column(nullable: true)]
    private ?float $ebitda = null;

    #[ORM\Column(nullable: true)]
    private ?float $profitMargin = null;

    #[ORM\Column(nullable: true)]
    private ?float $peRatio = null;

    #[ORM\Column(nullable: true)]
    private ?float $dividendYield = null;

    #[ORM\Column(nullable: true)]
    private ?float $roe = null;

    #[ORM\Column(nullable: true)]
    private ?float $debtToEquity = null;

    #[ORM\Column(nullable: true)]
    private ?float $currentRatio = null;

    #[ORM\Column(nullable: true)]
    private ?float $grossMargin = null;

    #[ORM\Column(nullable: true)]
    private ?float $operatingMargin = null;

    #[ORM\Column(nullable: true)]
    private ?float $marketCap = null;

    #[ORM\Column(nullable: true)]
    private ?float $shareholderEquity = null;

    #[ORM\Column(nullable: true)]
    private ?float $longTermDebt = null;

    #[ORM\Column(nullable: true)]
    private ?float $totalAssets = null;

    #[ORM\Column(nullable: true)]
    private ?float $totalLiabilities = null;

    #[ORM\Column(nullable: true)]
    private ?float $cashAndEquivalents = null;

    #[ORM\Column(nullable: true)]
    private ?float $operatingCashFlow = null;

    #[ORM\Column(nullable: true)]
    private ?float $freeCashFlow = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $highlights = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $risks = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $source = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

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

    public function getReportType(): ?string
    {
        return $this->reportType;
    }

    public function setReportType(string $reportType): static
    {
        $this->reportType = $reportType;

        return $this;
    }

    public function getReportDate(): ?\DateTimeInterface
    {
        return $this->reportDate;
    }

    public function setReportDate(?\DateTimeInterface $reportDate): static
    {
        $this->reportDate = $reportDate;

        return $this;
    }

    public function getRevenue(): ?float
    {
        return $this->revenue;
    }

    public function setRevenue(?float $revenue): static
    {
        $this->revenue = $revenue;

        return $this;
    }

    public function getNetIncome(): ?float
    {
        return $this->netIncome;
    }

    public function setNetIncome(?float $netIncome): static
    {
        $this->netIncome = $netIncome;

        return $this;
    }

    public function getEps(): ?float
    {
        return $this->eps;
    }

    public function setEps(?float $eps): static
    {
        $this->eps = $eps;

        return $this;
    }

    public function getEbitda(): ?float
    {
        return $this->ebitda;
    }

    public function setEbitda(?float $ebitda): static
    {
        $this->ebitda = $ebitda;

        return $this;
    }

    public function getFiscalQuarter(): ?string
    {
        return $this->fiscalQuarter;
    }

    public function setFiscalQuarter(?string $fiscalQuarter): static
    {
        $this->fiscalQuarter = $fiscalQuarter;

        return $this;
    }

    public function getFiscalYear(): ?int
    {
        return $this->fiscalYear;
    }

    public function setFiscalYear(?int $fiscalYear): static
    {
        $this->fiscalYear = $fiscalYear;

        return $this;
    }

    public function getProfitMargin(): ?float
    {
        return $this->profitMargin;
    }

    public function setProfitMargin(?float $profitMargin): static
    {
        $this->profitMargin = $profitMargin;

        return $this;
    }

    public function getPeRatio(): ?float
    {
        return $this->peRatio;
    }

    public function setPeRatio(?float $peRatio): static
    {
        $this->peRatio = $peRatio;

        return $this;
    }

    public function getDividendYield(): ?float
    {
        return $this->dividendYield;
    }

    public function setDividendYield(?float $dividendYield): static
    {
        $this->dividendYield = $dividendYield;

        return $this;
    }

    public function getRoe(): ?float
    {
        return $this->roe;
    }

    public function setRoe(?float $roe): static
    {
        $this->roe = $roe;

        return $this;
    }

    public function getDebtToEquity(): ?float
    {
        return $this->debtToEquity;
    }

    public function setDebtToEquity(?float $debtToEquity): static
    {
        $this->debtToEquity = $debtToEquity;

        return $this;
    }

    public function getCurrentRatio(): ?float
    {
        return $this->currentRatio;
    }

    public function setCurrentRatio(?float $currentRatio): static
    {
        $this->currentRatio = $currentRatio;

        return $this;
    }

    public function getGrossMargin(): ?float
    {
        return $this->grossMargin;
    }

    public function setGrossMargin(?float $grossMargin): static
    {
        $this->grossMargin = $grossMargin;

        return $this;
    }

    public function getOperatingMargin(): ?float
    {
        return $this->operatingMargin;
    }

    public function setOperatingMargin(?float $operatingMargin): static
    {
        $this->operatingMargin = $operatingMargin;

        return $this;
    }

    public function getMarketCap(): ?float
    {
        return $this->marketCap;
    }

    public function setMarketCap(?float $marketCap): static
    {
        $this->marketCap = $marketCap;

        return $this;
    }

    public function getShareholderEquity(): ?float
    {
        return $this->shareholderEquity;
    }

    public function setShareholderEquity(?float $shareholderEquity): static
    {
        $this->shareholderEquity = $shareholderEquity;

        return $this;
    }

    public function getLongTermDebt(): ?float
    {
        return $this->longTermDebt;
    }

    public function setLongTermDebt(?float $longTermDebt): static
    {
        $this->longTermDebt = $longTermDebt;

        return $this;
    }

    public function getTotalAssets(): ?float
    {
        return $this->totalAssets;
    }

    public function setTotalAssets(?float $totalAssets): static
    {
        $this->totalAssets = $totalAssets;

        return $this;
    }

    public function getTotalLiabilities(): ?float
    {
        return $this->totalLiabilities;
    }

    public function setTotalLiabilities(?float $totalLiabilities): static
    {
        $this->totalLiabilities = $totalLiabilities;

        return $this;
    }

    public function getCashAndEquivalents(): ?float
    {
        return $this->cashAndEquivalents;
    }

    public function setCashAndEquivalents(?float $cashAndEquivalents): static
    {
        $this->cashAndEquivalents = $cashAndEquivalents;

        return $this;
    }

    public function getOperatingCashFlow(): ?float
    {
        return $this->operatingCashFlow;
    }

    public function setOperatingCashFlow(?float $operatingCashFlow): static
    {
        $this->operatingCashFlow = $operatingCashFlow;

        return $this;
    }

    public function getFreeCashFlow(): ?float
    {
        return $this->freeCashFlow;
    }

    public function setFreeCashFlow(?float $freeCashFlow): static
    {
        $this->freeCashFlow = $freeCashFlow;

        return $this;
    }

    public function getHighlights(): ?string
    {
        return $this->highlights;
    }

    public function setHighlights(?string $highlights): static
    {
        $this->highlights = $highlights;

        return $this;
    }

    public function getRisks(): ?string
    {
        return $this->risks;
    }

    public function setRisks(?string $risks): static
    {
        $this->risks = $risks;

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
}
