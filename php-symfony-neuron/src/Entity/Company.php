<?php

namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\SecFiling;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\StockPrice;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
class Company
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $tickerSymbol = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $industry = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sector = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $headquarters = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $foundedYear = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $employees = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'company', targetEntity: FinancialData::class, cascade: ['persist', 'remove'])]
    private Collection $financialData;

    #[ORM\OneToMany(mappedBy: 'company', targetEntity: ExecutiveProfile::class, cascade: ['persist', 'remove'])]
    private Collection $executiveProfiles;

    #[ORM\OneToMany(mappedBy: 'company', targetEntity: CompetitorAnalysis::class, cascade: ['persist', 'remove'])]
    private Collection $competitorAnalyses;

    #[ORM\OneToMany(mappedBy: 'company', targetEntity: ResearchReport::class, cascade: ['persist', 'remove'])]
    private Collection $researchReports;

    #[ORM\OneToMany(mappedBy: 'company', targetEntity: StockPrice::class, cascade: ['persist', 'remove'])]
    private Collection $stockPrices;

    #[ORM\OneToMany(mappedBy: 'company', targetEntity: SecFiling::class, cascade: ['persist', 'remove'])]
    private Collection $secFilings;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->financialData = new ArrayCollection();
        $this->executiveProfiles = new ArrayCollection();
        $this->competitorAnalyses = new ArrayCollection();
        $this->researchReports = new ArrayCollection();
        $this->stockPrices = new ArrayCollection();
        $this->secFilings = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getTickerSymbol(): ?string
    {
        return $this->tickerSymbol;
    }

    public function setTickerSymbol(?string $tickerSymbol): static
    {
        $this->tickerSymbol = $tickerSymbol;

        return $this;
    }

    public function getIndustry(): ?string
    {
        return $this->industry;
    }

    public function setIndustry(?string $industry): static
    {
        $this->industry = $industry;

        return $this;
    }

    public function getSector(): ?string
    {
        return $this->sector;
    }

    public function setSector(?string $sector): static
    {
        $this->sector = $sector;

        return $this;
    }

    public function getHeadquarters(): ?string
    {
        return $this->headquarters;
    }

    public function setHeadquarters(?string $headquarters): static
    {
        $this->headquarters = $headquarters;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getFoundedYear(): ?int
    {
        return $this->foundedYear;
    }

    public function setFoundedYear(?int $foundedYear): static
    {
        $this->foundedYear = $foundedYear;

        return $this;
    }

    public function getEmployees(): ?int
    {
        return $this->employees;
    }

    public function setEmployees(?int $employees): static
    {
        $this->employees = $employees;

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

    /**
     * @return Collection<int, FinancialData>
     */
    public function getFinancialData(): Collection
    {
        return $this->financialData;
    }

    public function addFinancialData(FinancialData $financialData): static
    {
        if (!$this->financialData->contains($financialData)) {
            $this->financialData->add($financialData);
            $financialData->setCompany($this);
        }

        return $this;
    }

    public function removeFinancialData(FinancialData $financialData): static
    {
        if ($this->financialData->removeElement($financialData)) {
            // set the owning side to null (unless already changed)
            if ($financialData->getCompany() === $this) {
                $financialData->setCompany(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ExecutiveProfile>
     */
    public function getExecutiveProfiles(): Collection
    {
        return $this->executiveProfiles;
    }

    public function addExecutiveProfile(ExecutiveProfile $executiveProfile): static
    {
        if (!$this->executiveProfiles->contains($executiveProfile)) {
            $this->executiveProfiles->add($executiveProfile);
            $executiveProfile->setCompany($this);
        }

        return $this;
    }

    public function removeExecutiveProfile(ExecutiveProfile $executiveProfile): static
    {
        if ($this->executiveProfiles->removeElement($executiveProfile)) {
            // set the owning side to null (unless already changed)
            if ($executiveProfile->getCompany() === $this) {
                $executiveProfile->setCompany(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CompetitorAnalysis>
     */
    public function getCompetitorAnalyses(): Collection
    {
        return $this->competitorAnalyses;
    }

    public function addCompetitorAnalysis(CompetitorAnalysis $competitorAnalysis): static
    {
        if (!$this->competitorAnalyses->contains($competitorAnalysis)) {
            $this->competitorAnalyses->add($competitorAnalysis);
            $competitorAnalysis->setCompany($this);
        }

        return $this;
    }

    public function removeCompetitorAnalysis(CompetitorAnalysis $competitorAnalysis): static
    {
        if ($this->competitorAnalyses->removeElement($competitorAnalysis)) {
            // set the owning side to null (unless already changed)
            if ($competitorAnalysis->getCompany() === $this) {
                $competitorAnalysis->setCompany(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ResearchReport>
     */
    public function getResearchReports(): Collection
    {
        return $this->researchReports;
    }

    public function addResearchReport(ResearchReport $researchReport): static
    {
        if (!$this->researchReports->contains($researchReport)) {
            $this->researchReports->add($researchReport);
            $researchReport->setCompany($this);
        }

        return $this;
    }

    public function removeResearchReport(ResearchReport $researchReport): static
    {
        if ($this->researchReports->removeElement($researchReport)) {
            // set the owning side to null (unless already changed)
            if ($researchReport->getCompany() === $this) {
                $researchReport->setCompany(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, StockPrice>
     */
    public function getStockPrices(): Collection
    {
        return $this->stockPrices;
    }

    public function addStockPrice(StockPrice $stockPrice): static
    {
        if (!$this->stockPrices->contains($stockPrice)) {
            $this->stockPrices->add($stockPrice);
            $stockPrice->setCompany($this);
        }

        return $this;
    }

    public function removeStockPrice(StockPrice $stockPrice): static
    {
        if ($this->stockPrices->removeElement($stockPrice)) {
            // set the owning side to null (unless already changed)
            if ($stockPrice->getCompany() === $this) {
                $stockPrice->setCompany(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, SecFiling>
     */
    public function getSecFilings(): Collection
    {
        return $this->secFilings;
    }

    public function addSecFiling(SecFiling $secFiling): static
    {
        if (!$this->secFilings->contains($secFiling)) {
            $this->secFilings->add($secFiling);
            $secFiling->setCompany($this);
        }

        return $this;
    }

    public function removeSecFiling(SecFiling $secFiling): static
    {
        if ($this->secFilings->removeElement($secFiling)) {
            // set the owning side to null (unless already changed)
            if ($secFiling->getCompany() === $this) {
                $secFiling->setCompany(null);
            }
        }

        return $this;
    }
}
