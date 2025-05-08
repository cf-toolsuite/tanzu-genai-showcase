<?php

namespace App\Entity;

use App\Repository\AnalystRatingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnalystRatingRepository::class)]
#[ORM\Table(name: 'analyst_rating')]
class AnalystRating
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'analystRatings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Company $company = null;

    #[ORM\Column(length: 255)]
    private ?string $firmName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $analystName = null;

    #[ORM\Column(length: 50)]
    private ?string $rating = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $previousRating = null;

    #[ORM\Column(nullable: true)]
    private ?float $priceTarget = null;

    #[ORM\Column(nullable: true)]
    private ?float $previousPriceTarget = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $ratingDate = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentary = null;

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

    public function getFirmName(): ?string
    {
        return $this->firmName;
    }

    public function setFirmName(string $firmName): self
    {
        $this->firmName = $firmName;

        return $this;
    }

    public function getAnalystName(): ?string
    {
        return $this->analystName;
    }

    public function setAnalystName(?string $analystName): self
    {
        $this->analystName = $analystName;

        return $this;
    }

    public function getRating(): ?string
    {
        return $this->rating;
    }

    public function setRating(string $rating): self
    {
        $this->rating = $rating;

        return $this;
    }

    public function getPreviousRating(): ?string
    {
        return $this->previousRating;
    }

    public function setPreviousRating(?string $previousRating): self
    {
        $this->previousRating = $previousRating;

        return $this;
    }

    public function getPriceTarget(): ?float
    {
        return $this->priceTarget;
    }

    public function setPriceTarget(?float $priceTarget): self
    {
        $this->priceTarget = $priceTarget;

        return $this;
    }

    public function getPreviousPriceTarget(): ?float
    {
        return $this->previousPriceTarget;
    }

    public function setPreviousPriceTarget(?float $previousPriceTarget): self
    {
        $this->previousPriceTarget = $previousPriceTarget;

        return $this;
    }

    public function getRatingDate(): ?\DateTimeInterface
    {
        return $this->ratingDate;
    }

    public function setRatingDate(\DateTimeInterface $ratingDate): self
    {
        $this->ratingDate = $ratingDate;

        return $this;
    }

    public function getCommentary(): ?string
    {
        return $this->commentary;
    }

    public function setCommentary(?string $commentary): self
    {
        $this->commentary = $commentary;

        return $this;
    }
}
