<?php

namespace App\Resource\Ingest;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use App\State\Ingest\CurrentInstanceProvider;
use App\State\Ingest\RegisterInstanceProcessor;
use App\State\Ingest\RetireInstanceProcessor;
use App\Validator\AcceptedPolicyVersion;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Registration of an instance with the collector, when its admin first enables forwarding.
 */
#[ApiResource(
    shortName: 'InstanceRegistration',
    operations: [
        new Post(
            uriTemplate: '/v1/instances',
            status: 201,
            description: 'Public. Registers an instance; the secret is returned only once. 422 if acceptedPolicyVersion is not accepted, 429 when the optional per-IP limit is hit.',
            output: RegisteredInstance::class,
            openapi: new OpenApiOperation(responses: ['413' => new OpenApiResponse('Request body above 32 KiB'), '429' => new OpenApiResponse('Rate limit (only when enabled); see Retry-After')]),
            processor: RegisterInstanceProcessor::class,
        ),
        new Delete(
            uriTemplate: '/v1/instances/me',
            description: 'Instance bearer. Retires the calling instance and its secret. Its reports stay and can be deleted by report id.',
            read: true,
            openapi: new OpenApiOperation(responses: ['401' => new OpenApiResponse('Missing or unknown instance secret'), '403' => new OpenApiResponse('Blocked or retired instance')]),
            provider: CurrentInstanceProvider::class,
            processor: RetireInstanceProcessor::class,
        ),
    ],
    formats: ['json' => ['application/json']],
)]
final class InstanceRegistration
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    public string $serverVersion;

    /** The collector policy version the instance's admin accepted */
    #[Assert\NotBlank]
    #[AcceptedPolicyVersion]
    public string $acceptedPolicyVersion;
}
