<?php

namespace App\Resource\Ingest;

use Symfony\Component\Uid\Uuid;

/**
 * Response of POST /v1/instances. The secret is the instance's bearer token and is never shown again.
 */
final readonly class RegisteredInstance
{
    public function __construct(
        public Uuid $instanceId,
        public string $instanceSecret,
    ) {
    }
}
