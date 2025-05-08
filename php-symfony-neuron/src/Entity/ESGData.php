<?php

namespace App\Entity;

use App\Repository\ESGDataRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ESGDataRepository::class)]
#[ORM\Table(name: 'esg_data')]
class ESGData
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'esgData')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Company $company = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $totalScore = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $environmentScore = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $socialScore = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $governanceScore = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $peerComparisonTotal = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $lastUpdated = null;

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

    public function getTotalScore(): ?float
    {
        return $this->totalScore;
    }

    public function setTotalScore(?float $totalScore): self
    {
        $this->totalScore = $totalScore;

        return $this;
    }

    public function getEnvironmentScore(): ?float
    {
        return $this->environmentScore;
    }

    public function setEnvironmentScore(?float $environmentScore): self
    {
        $this->environmentScore = $environmentScore;

        return $this;
    }

    public function getSocialScore(): ?float
    {
        return $this->socialScore;
    }

    public function setSocialScore(?float $socialScore): self
    {
        $this->socialScore = $socialScore;

        return $this;
    }

    public function getGovernanceScore(): ?float
    {
        return $this->governanceScore;
    }

    public function setGovernanceScore(?float $governanceScore): self
    {
        $this->governanceScore = $governanceScore;

        return $this;
    }

    public function getPeerComparisonTotal(): ?int
    {
        return $this->peerComparisonTotal;
    }

    public function setPeerComparisonTotal(?int $peerComparisonTotal): self
    {
        $this->peerComparisonTotal = $peerComparisonTotal;

        return $this;
    }

    public function getLastUpdated(): ?\DateTimeInterface
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(\DateTimeInterface $lastUpdated): self
    {
        $this->lastUpdated = $lastUpdated;

        return $this;
    }
}
