<?php

namespace App\Resource\Admin;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\State\Admin\ReportStatsProvider;

/**
 * Report counts per period (by receivedAt), optionally grouped by a dimension. Private: maintainers with ROLE_ADMIN.
 */
#[ApiResource(
    shortName: 'ReportStats',
    operations: [
        new GetCollection(
            uriTemplate: '/admin/report_stats',
            description: 'ROLE_ADMIN. Reports and distinct reporting instances per period and group, ordered by period, then by count. Without since, the last 30 days, 12 weeks or 12 months.',
            paginationEnabled: false,
            parameters: [
                'since' => new QueryParameter(schema: ['type' => 'string', 'format' => 'date-time'], description: 'Received at or after (date or date-time)'),
                'until' => new QueryParameter(schema: ['type' => 'string', 'format' => 'date-time'], description: 'Received before (date or date-time); default now'),
                'groupBy' => new QueryParameter(schema: ['type' => 'string', 'enum' => ReportStatsProvider::GROUP_BY], description: 'Dimension of key; without it, key is null'),
                'period' => new QueryParameter(schema: ['type' => 'string', 'enum' => ReportStatsProvider::PERIODS, 'default' => 'day'], description: 'Bucket size'),
            ],
            provider: ReportStatsProvider::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    security: "is_granted('ROLE_ADMIN')",
)]
final class ReportStats
{
    public function __construct(
        /** Value of the groupBy dimension */
        public ?string $key,
        /** Start of the bucket */
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date'])]
        public string $period,
        public int $reports,
        /** Distinct reporting instances */
        public int $instances,
    ) {
    }
}
