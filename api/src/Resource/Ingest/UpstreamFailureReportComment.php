<?php

namespace App\Resource\Ingest;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\State\Ingest\AddCommentProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A comment the instance's admin adds to an already forwarded report.
 */
#[ApiResource(
    shortName: 'UpstreamFailureReportComment',
    operations: [
        new Post(
            uriTemplate: '/v1/reports/{id}/comments',
            uriVariables: ['id' => new Link(fromClass: UpstreamFailureReport::class)],
            status: 201,
            description: 'Instance bearer, own reports only (otherwise 404). 429 when the optional per-instance limit is hit.',
            output: IngestedReportComment::class,
            openapi: new OpenApiOperation(responses: ['401' => new OpenApiResponse('Missing or unknown instance secret'), '403' => new OpenApiResponse('Blocked or retired instance'), '404' => new OpenApiResponse('Unknown report, or not the calling instance\'s'), '413' => new OpenApiResponse('Request body above 32 KiB'), '429' => new OpenApiResponse('Rate limit (only when enabled); see Retry-After')]),
            read: false,
            processor: AddCommentProcessor::class,
        ),
    ],
    formats: ['json' => ['application/json']],
)]
final class UpstreamFailureReportComment
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 4000)]
    public string $comment;

    /** When the comment was written on the instance */
    #[Assert\NotNull]
    public \DateTimeImmutable $createdAt;
}
