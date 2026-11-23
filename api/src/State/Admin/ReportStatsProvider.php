<?php

namespace App\State\Admin;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Resource\Admin\ReportStats;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * GET /admin/report_stats: one grouped SQL query over report.
 *
 * @implements ProviderInterface<ReportStats>
 */
final readonly class ReportStatsProvider implements ProviderInterface
{
    /** groupBy value => column */
    private const array COLUMNS = [
        'host' => 'host',
        'site' => 'site',
        'kind' => 'kind',
        'downloader' => 'downloader',
        'errorCategory' => 'error_category',
        'status' => 'status',
    ];
    public const array GROUP_BY = ['host', 'site', 'kind', 'downloader', 'errorCategory', 'status'];
    public const array PERIODS = ['day', 'week', 'month'];
    private const array DEFAULT_RANGE = ['day' => '-29 days', 'week' => '-11 weeks', 'month' => '-11 months'];

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return list<ReportStats>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $query = ($context['request'] ?? null) instanceof Request ? $context['request']->query : null;
        $period = (string) ($query?->get('period') ?: 'day');
        $groupBy = $query?->get('groupBy') ?: null;
        if (!\in_array($period, self::PERIODS, true)) {
            throw new UnprocessableEntityHttpException('period must be one of: '.implode(', ', self::PERIODS).'.');
        }
        if (null !== $groupBy && !isset(self::COLUMNS[$groupBy])) {
            throw new UnprocessableEntityHttpException('groupBy must be one of: '.implode(', ', self::GROUP_BY).'.');
        }
        $until = $this->date($query?->get('until'), 'until') ?? new \DateTimeImmutable();
        $since = $this->date($query?->get('since'), 'since')
            ?? new \DateTimeImmutable('today')->modify(self::DEFAULT_RANGE[$period]);

        $key = null !== $groupBy ? 'r.'.self::COLUMNS[$groupBy] : 'NULL';
        $rows = $this->connection->fetchAllAssociative(
            "SELECT date_trunc(:period, r.received_at)::date AS period, {$key} AS key, COUNT(*) AS reports, COUNT(DISTINCT r.instance_id) AS instances
             FROM report r
             WHERE r.received_at >= :since AND r.received_at < :until
             GROUP BY 1, 2
             ORDER BY 1, 3 DESC, 2",
            ['period' => $period, 'since' => $since->format('Y-m-d H:i:s'), 'until' => $until->format('Y-m-d H:i:s')],
        );

        return array_map(static fn (array $row): ReportStats => new ReportStats(
            null !== $row['key'] ? (string) $row['key'] : null,
            (string) $row['period'],
            (int) $row['reports'],
            (int) $row['instances'],
        ), $rows);
    }

    private function date(mixed $value, string $name): ?\DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }
        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Exception) {
            throw new UnprocessableEntityHttpException(\sprintf('%s must be a date or date-time.', $name));
        }
    }
}
