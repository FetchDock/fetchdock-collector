<?php

namespace App\Resource\Ingest;

use Symfony\Component\Uid\Uuid;

/**
 * Response of POST /v1/reports/{id}/comments.
 */
final readonly class IngestedReportComment
{
    public function __construct(public Uuid $id)
    {
    }
}
