<?php

namespace App\Enum;

/**
 * Kinds of failure reports, as sent by instances (failure reports spec, ADR 0012).
 */
enum ReportKind: string
{
    case JobFailed = 'job_failed';
    case UnsupportedUrl = 'unsupported_url';
}
