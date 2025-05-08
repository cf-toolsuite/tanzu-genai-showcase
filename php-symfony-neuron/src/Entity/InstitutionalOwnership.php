<?php

namespace App\Entity;

use App\Repository\InstitutionalOwnershipRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InstitutionalOwnershipRepository::class)]
#[ORM\Table(name: 'institutional_ownership')]
class InstitutionalOwnership
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'institutionalOwnerships')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Company $company = null;

    #[ORM\Column(length: 255)]
    private ?string $institutionName = null;

    #[ORM\Column]
    private ?int $shares = null;

    #[ORM\Column]
    private ?float $value = null;

    #[ORM\Column]
    private ?float $percentageOwned = null;

    #[ORM\Column(nullable: true)]
    private ?int $previousShares = null;

    #[ORM\Column(nullable: true)]
    private ?float $percentageChange = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $reportDate = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): self
    {
        $this->company = $company;

        return $this;
    }

    public function getInstitutionName(): ?string
    {
        return $this->institutionName;
    }

    public function setInstitutionName(string $institutionName): self
    {
        $this->institutionName = $institutionName;

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

    public function getValue(): ?float
    {
        return $this->value;
    }

    public function setValue(float $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getPercentageOwned(): ?float
    {
        return $this->percentageOwned;
    }

    public function setPercentageOwned(float $percentageOwned): self
    {
        $this->percentageOwned = $percentageOwned;

        return $this;
    }

    public function getPreviousShares(): ?int
    {
        return $this->previousShares;
    }

    public function setPreviousShares(?int $previousShares): self
    {
        $this->previousShares = $previousShares;

        return $this;
    }

    public function getPercentageChange(): ?float
    {
        return $this->percentageChange;
    }

    public function setPercentageChange(?float $percentageChange): self
    {
        $this->percentageChange = $percentageChange;

        return $this;
    }

    public function getReportDate(): ?\DateTimeInterface
    {
        return $this->reportDate;
    }

    public function setReportDate(\DateTimeInterface $reportDate): self
    {
        $this->reportDate = $reportDate;

        return $this;
    }
}
