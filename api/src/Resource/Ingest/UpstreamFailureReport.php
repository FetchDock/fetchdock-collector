<?php

namespace App\Resource\Ingest;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use App\Enum\ReportKind;
use App\State\Ingest\IngestReportProcessor;
use App\State\Ingest\DeleteReportProcessor;
use App\State\Ingest\OwnedReportProvider;
use App\Validator\AcceptedPolicyVersion;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A failure report forwarded by an instance with its user's consent (schemaVersion 1, failure reports spec). The
 * instance is identified by its bearer secret, not by a field. Stored verbatim.
 */
#[ApiResource(
    shortName: 'UpstreamFailureReport',
    operations: [
        new Post(
            uriTemplate: '/v1/reports',
            status: 201,
            description: 'Instance bearer. Stores a forwarded report and returns its id (the instance\'s upstreamId). 422 if consent.policyVersion or operatorPolicyVersion is not accepted, 429 when the optional per-instance limit is hit.',
            output: IngestedReport::class,
            openapi: new OpenApiOperation(responses: ['401' => new OpenApiResponse('Missing or unknown instance secret'), '403' => new OpenApiResponse('Blocked or retired instance'), '413' => new OpenApiResponse('Request body above 32 KiB'), '429' => new OpenApiResponse('Rate limit (only when enabled); see Retry-After')]),
            processor: IngestReportProcessor::class,
        ),
        new Delete(
            uriTemplate: '/v1/reports/{id}',
            description: 'Instance bearer, own reports only. Hard delete with its comments (withdrawal of consent). 404 when unknown or not the instance\'s.',
            provider: OwnedReportProvider::class,
            openapi: new OpenApiOperation(responses: ['401' => new OpenApiResponse('Missing or unknown instance secret'), '403' => new OpenApiResponse('Blocked or retired instance'), '404' => new OpenApiResponse('Unknown report, or not the calling instance\'s')]),
            processor: DeleteReportProcessor::class,
        ),
    ],
    formats: ['json' => ['application/json']],
)]
final class UpstreamFailureReport
{
    public const int SCHEMA_VERSION = 1;

    /** The report's id in the URI only; never part of the payload */
    #[ApiProperty(readable: false, writable: false, identifier: true)]
    public ?Uuid $id = null;

    #[Assert\NotNull]
    #[Assert\EqualTo(self::SCHEMA_VERSION, message: 'Unsupported schemaVersion; this endpoint accepts {{ compared_value }}.')]
    public int $schemaVersion;

    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    public string $serverVersion;

    #[Assert\NotNull]
    public ReportKind $kind;

    #[Assert\NotBlank]
    #[Assert\Length(max: 2048)]
    #[Assert\Url(protocols: ['http', 'https'], requireTld: false)]
    public string $url;

    /** Derived from url by the instance */
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $host;

    /** Site slug on the instance, null if unknown */
    #[Assert\Length(max: 255)]
    public ?string $site = null;

    #[Assert\Length(max: 512)]
    public ?string $userAgent = null;

    #[Assert\Length(max: 2048)]
    #[Assert\Url(protocols: ['http', 'https'], requireTld: false)]
    public ?string $referrer = null;

    /** job_failed only */
    #[Assert\Valid]
    #[Assert\Expression('this.diagnostics === null or this.kindValue() === "job_failed"', message: 'diagnostics are only allowed on job_failed reports.')]
    public ?ReportDiagnostics $diagnostics = null;

    /** The user's comment */
    #[Assert\Length(max: 2000)]
    public ?string $comment = null;

    /** The instance admin's comment for the developers */
    #[Assert\Length(max: 4000)]
    public ?string $adminComment = null;

    /** When the report was created on the instance */
    #[Assert\NotNull]
    public \DateTimeImmutable $createdAt;

    #[Assert\NotNull]
    #[Assert\Valid]
    public ReportConsent $consent;

    /** The collector policy version the instance's admin accepted */
    #[Assert\NotBlank]
    #[AcceptedPolicyVersion]
    public string $operatorPolicyVersion;

    /** For the diagnostics constraint: the kind, or null while it is missing */
    public function kindValue(): ?string
    {
        return isset($this->kind) ? $this->kind->value : null;
    }
}
