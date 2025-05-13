<?php

namespace App\Entity;

use App\Repository\SecFilingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SecFilingRepository::class)]
#[ORM\Table(name: 'sec_filing')]
class SecFiling
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'secFilings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Company $company = null;

    #[ORM\Column(length: 20)]
    private ?string $type = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $formType = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $filingDate = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $url = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $documentUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $htmlUrl = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private array $exhibits = [];
    
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $accessionNumber = null;
    
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $fileNumber = null;
    
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $textUrl = null;
    
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;
    
    #[ORM\Column(type: 'json', nullable: true)]
    private array $sections = [];
    
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $summary = null;
    
    #[ORM\Column(type: 'json', nullable: true)]
    private array $keyFindings = [];
    
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isProcessed = false;
    
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;
    
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $reportDate = null;
    
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $fiscalYear = null;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getFilingDate(): ?\DateTimeInterface
    {
        return $this->filingDate;
    }

    public function setFilingDate(\DateTimeInterface $filingDate): static
    {
        $this->filingDate = $filingDate;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getExhibits(): ?array
    {
        return $this->exhibits;
    }

    public function setExhibits(?array $exhibits): static
    {
        $this->exhibits = $exhibits;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->description;
    }

    public function getFormType(): ?string
    {
        return $this->formType;
    }

    public function setFormType(?string $formType): static
    {
        $this->formType = $formType;

        return $this;
    }

    public function getDocumentUrl(): ?string
    {
        return $this->documentUrl;
    }

    public function setDocumentUrl(?string $documentUrl): static
    {
        $this->documentUrl = $documentUrl;

        return $this;
    }

    public function getHtmlUrl(): ?string
    {
        return $this->htmlUrl;
    }

    public function setHtmlUrl(?string $htmlUrl): static
    {
        $this->htmlUrl = $htmlUrl;

        return $this;
    }
    
    public function getAccessionNumber(): ?string
    {
        return $this->accessionNumber;
    }

    public function setAccessionNumber(?string $accessionNumber): static
    {
        $this->accessionNumber = $accessionNumber;

        return $this;
    }

    public function getFileNumber(): ?string
    {
        return $this->fileNumber;
    }

    public function setFileNumber(?string $fileNumber): static
    {
        $this->fileNumber = $fileNumber;

        return $this;
    }

    public function getTextUrl(): ?string
    {
        return $this->textUrl;
    }

    public function setTextUrl(?string $textUrl): static
    {
        $this->textUrl = $textUrl;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getSections(): array
    {
        return $this->sections;
    }

    public function setSections(array $sections): static
    {
        $this->sections = $sections;

        return $this;
    }
    
    public function getSection(string $key): ?string
    {
        return $this->sections[$key] ?? null;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): static
    {
        $this->summary = $summary;

        return $this;
    }

    public function getKeyFindings(): array
    {
        return $this->keyFindings;
    }

    public function setKeyFindings(array $keyFindings): static
    {
        $this->keyFindings = $keyFindings;

        return $this;
    }

    public function getIsProcessed(): bool
    {
        return $this->isProcessed;
    }

    public function setIsProcessed(bool $isProcessed): static
    {
        $this->isProcessed = $isProcessed;

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

    public function getReportDate(): ?\DateTimeInterface
    {
        return $this->reportDate;
    }

    public function setReportDate(?\DateTimeInterface $reportDate): static
    {
        $this->reportDate = $reportDate;

        return $this;
    }

    public function getFiscalYear(): ?string
    {
        return $this->fiscalYear;
    }

    public function setFiscalYear(?string $fiscalYear): static
    {
        $this->fiscalYear = $fiscalYear;

        return $this;
    }
}
