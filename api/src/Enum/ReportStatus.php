<?php

namespace App\Enum;

/**
 * Triage status of a report, set by maintainers.
 */
enum ReportStatus: string
{
    case New = 'new';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';
    case WontFix = 'wont_fix';
    case Duplicate = 'duplicate';

    /**
     * Terminal statuses: reports with one of them are purged after the retention period.
     */
    public function isTerminal(): bool
    {
        return \in_array($this, [self::Resolved, self::WontFix, self::Duplicate], true);
    }
}
