<?php

namespace App\Resource\Ingest;

use Symfony\Component\Uid\Uuid;

/**
 * Response of POST /v1/reports: the id the instance stores as upstreamId.
 */
final readonly class IngestedReport
{
    public function __construct(public Uuid $id)
    {
    }
}
