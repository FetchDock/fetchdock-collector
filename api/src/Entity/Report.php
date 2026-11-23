<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BackedEnumFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Enum\ReportKind;
use App\Enum\ReportStatus;
use App\Repository\ReportRepository;
use App\State\Admin\TriageReportProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A failure report forwarded by an instance: the UpstreamFailureReport payload stored verbatim, plus triage fields
 * (failure reports spec, ADR 0012). Deleted with its comments on request.
 */
#[ORM\Entity(repositoryClass: ReportRepository::class)]
#[ORM\Index(fields: ['receivedAt'])]
#[ORM\Index(fields: ['status'])]
#[ORM\Index(fields: ['host'])]
#[ORM\Index(fields: ['errorCategory'])]
#[ORM\Index(fields: ['downloader'])]
#[ApiResource(
    shortName: 'AdminReport',
    operations: [
        new GetCollection(uriTemplate: '/admin/reports', description: 'ROLE_MAINTAINER. Forwarded reports, newest first.'),
        new Get(uriTemplate: '/admin/reports/{id}', description: 'ROLE_MAINTAINER.'),
        new Patch(uriTemplate: '/admin/reports/{id}', description: 'ROLE_MAINTAINER. Triage: status and maintainerNote.', processor: TriageReportProcessor::class),
        new Delete(
            uriTemplate: '/admin/reports/{id}',
            description: 'ROLE_ADMIN. Hard delete with comments, for deletion requests sent by email (report id).',
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
    normalizationContext: ['groups' => ['admin_report:read'], 'skip_null_values' => false],
    denormalizationContext: ['groups' => ['admin_report:write']],
    order: ['receivedAt' => 'DESC'],
    security: "is_granted('ROLE_MAINTAINER')",
)]
#[ApiFilter(BackedEnumFilter::class, properties: ['kind', 'status'])]
#[ApiFilter(SearchFilter::class, properties: ['host' => 'exact', 'site' => 'exact', 'downloader' => 'exact', 'errorCategory' => 'exact', 'instance' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['receivedAt', 'reportedAt', 'updatedAt'])]
class Report
{
    /** Random (v4) UUID; the instance stores it as upstreamId */
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['admin_report:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'reports')]
    #[ORM\JoinColumn(nullable: false)]
    private Instance $instance;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Groups(['admin_report:read'])]
    private int $schemaVersion = 1;

    #[ORM\Column(length: 64)]
    #[Groups(['admin_report:read'])]
    private string $serverVersion;

    #[ORM\Column(length: 32, enumType: ReportKind::class)]
    #[Groups(['admin_report:read'])]
    private ReportKind $kind;

    #[ORM\Column(length: 2048)]
    #[Groups(['admin_report:read'])]
    private string $url;

    #[ORM\Column(length: 255)]
    #[Groups(['admin_report:read'])]
    private string $host;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin_report:read'])]
    private ?string $site = null;

    #[ORM\Column(length: 512, nullable: true)]
    #[Groups(['admin_report:read'])]
    private ?string $userAgent = null;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Groups(['admin_report:read'])]
    private ?string $referrer = null;

    /** @var array<string, mixed>|null downloader, downloaderVersion, errorMessage, clientVersion */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    #[Groups(['admin_report:read'])]
    private ?array $diagnostics = null;

    /** diagnostics.downloader, denormalized for filters and stats */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $downloader = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['admin_report:read'])]
    private ?string $comment = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['admin_report:read'])]
    private ?string $adminComment = null;

    /** @var array{policyVersion: string, consentedAt: string} the user's consent */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    #[Groups(['admin_report:read'])]
    private array $consent;

    #[ORM\Column(length: 32)]
    #[Groups(['admin_report:read'])]
    private string $operatorPolicyVersion;

    /** Derived at ingest from diagnostics.errorMessage (collector.error_categories) */
    #[ORM\Column(length: 32, nullable: true)]
    #[Groups(['admin_report:read'])]
    private ?string $errorCategory = null;

    /** The report's createdAt on the instance */
    #[ORM\Column]
    #[Groups(['admin_report:read'])]
    private \DateTimeImmutable $reportedAt;

    #[ORM\Column]
    #[Groups(['admin_report:read'])]
    private \DateTimeImmutable $receivedAt;

    #[ORM\Column(length: 16, enumType: ReportStatus::class)]
    #[Groups(['admin_report:read', 'admin_report:write'])]
    #[Assert\NotNull]
    private ReportStatus $status = ReportStatus::New;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['admin_report:read', 'admin_report:write'])]
    #[Assert\Length(max: 4000)]
    private ?string $maintainerNote = null;

    #[ORM\Column]
    #[Groups(['admin_report:read'])]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, ReportComment> */
    #[ORM\OneToMany(targetEntity: ReportComment::class, mappedBy: 'report', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['receivedAt' => 'ASC'])]
    #[Groups(['admin_report:read'])]
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

    /**
     * The instance's id; ROLE_ADMIN only (AdminReportNormalizer sets it to null for other maintainers), never public.
     */
    #[Groups(['admin_report:read'])]
    #[SerializedName('instance')]
    #[ApiProperty(schema: ['type' => ['string', 'null'], 'format' => 'uuid'])]
    public function getInstanceId(): ?string
    {
        return $this->instance->getId()->toRfc4122();
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
        $downloader = $diagnostics['downloader'] ?? null;
        $this->downloader = \is_string($downloader) && '' !== $downloader ? mb_substr($downloader, 0, 255) : null;

        return $this;
    }

    public function getDownloader(): ?string
    {
        return $this->downloader;
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
