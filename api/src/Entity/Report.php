<?php

namespace App\Entity;

use App\Enum\ReportKind;
use App\Enum\ReportStatus;
use App\Repository\ReportRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A failure report forwarded by an instance: the UpstreamFailureReport payload stored verbatim, plus triage fields
 * (failure reports spec, ADR 0012). Deleted with its comments on request.
 */
#[ORM\Entity(repositoryClass: ReportRepository::class)]
#[ORM\Index(fields: ['receivedAt'])]
#[ORM\Index(fields: ['status'])]
#[ORM\Index(fields: ['host'])]
class Report
{
    /** Random (v4) UUID; the instance stores it as upstreamId */
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Instance $instance;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $schemaVersion = 1;

    #[ORM\Column(length: 64)]
    private string $serverVersion;

    #[ORM\Column(length: 32, enumType: ReportKind::class)]
    private ReportKind $kind;

    #[ORM\Column(length: 2048)]
    private string $url;

    #[ORM\Column(length: 255)]
    private string $host;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $site = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $referrer = null;

    /** @var array<string, mixed>|null downloader, downloaderVersion, errorMessage, clientVersion */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private ?array $diagnostics = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adminComment = null;

    /** @var array{policyVersion: string, consentedAt: string} the user's consent */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $consent;

    #[ORM\Column(length: 32)]
    private string $operatorPolicyVersion;

    /** Derived at ingest from diagnostics.errorMessage (collector.error_categories) */
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $errorCategory = null;

    /** The report's createdAt on the instance */
    #[ORM\Column]
    private \DateTimeImmutable $reportedAt;

    #[ORM\Column]
    private \DateTimeImmutable $receivedAt;

    #[ORM\Column(length: 16, enumType: ReportStatus::class)]
    private ReportStatus $status = ReportStatus::New;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $maintainerNote = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, ReportComment> */
    #[ORM\OneToMany(targetEntity: ReportComment::class, mappedBy: 'report', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['receivedAt' => 'ASC'])]
    private Collection $comments;

    /**
     * @param array{policyVersion: string, consentedAt: string} $consent
     */
    public function __construct(
        Instance $instance,
        ReportKind $kind,
        string $url,
        string $host,
        string $serverVersion,
        array $consent,
        string $operatorPolicyVersion,
        \DateTimeImmutable $reportedAt,
        ?\DateTimeImmutable $receivedAt = null,
    ) {
        $this->id = Uuid::v4();
        $this->instance = $instance;
        $this->kind = $kind;
        $this->url = $url;
        $this->host = $host;
        $this->serverVersion = $serverVersion;
        $this->consent = $consent;
        $this->operatorPolicyVersion = $operatorPolicyVersion;
        $this->reportedAt = $reportedAt;
        $this->receivedAt = $receivedAt ?? new \DateTimeImmutable();
        $this->updatedAt = $this->receivedAt;
        $this->comments = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getInstance(): Instance
    {
        return $this->instance;
    }

    public function getSchemaVersion(): int
    {
        return $this->schemaVersion;
    }

    public function setSchemaVersion(int $schemaVersion): static
    {
        $this->schemaVersion = $schemaVersion;

        return $this;
    }

    public function getServerVersion(): string
    {
        return $this->serverVersion;
    }

    public function getKind(): ReportKind
    {
        return $this->kind;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getSite(): ?string
    {
        return $this->site;
    }

    public function setSite(?string $site): static
    {
        $this->site = $site;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }

    public function setReferrer(?string $referrer): static
    {
        $this->referrer = $referrer;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getDiagnostics(): ?array
    {
        return $this->diagnostics;
    }

    /** @param array<string, mixed>|null $diagnostics */
    public function setDiagnostics(?array $diagnostics): static
    {
        $this->diagnostics = $diagnostics;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getAdminComment(): ?string
    {
        return $this->adminComment;
    }

    public function setAdminComment(?string $adminComment): static
    {
        $this->adminComment = $adminComment;

        return $this;
    }

    /** @return array{policyVersion: string, consentedAt: string} */
    public function getConsent(): array
    {
        return $this->consent;
    }

    public function getOperatorPolicyVersion(): string
    {
        return $this->operatorPolicyVersion;
    }

    public function getErrorCategory(): ?string
    {
        return $this->errorCategory;
    }

    public function setErrorCategory(?string $errorCategory): static
    {
        $this->errorCategory = $errorCategory;

        return $this;
    }

    public function getReportedAt(): \DateTimeImmutable
    {
        return $this->reportedAt;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function getStatus(): ReportStatus
    {
        return $this->status;
    }

    public function setStatus(ReportStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getMaintainerNote(): ?string
    {
        return $this->maintainerNote;
    }

    public function setMaintainerNote(?string $maintainerNote): static
    {
        $this->maintainerNote = $maintainerNote;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /** @return Collection<int, ReportComment> */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(ReportComment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
        }

        return $this;
    }
}
