<?php

namespace App\State\Admin;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Resource\Admin\AdminIntegrity;
use App\Resource\Admin\AdminIntegrityCheck;
use App\Service\Integrity\IntegrityReport;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * GET /admin/integrity.
 *
 * @implements ProviderInterface<AdminIntegrity>
 */
final readonly class AdminIntegrityProvider implements ProviderInterface
{
    public function __construct(
        private IntegrityReport $report,
        #[Autowire('%app.version%')]
        private string $appVersion,
        #[Autowire('%app.integrity.check_online%')]
        private bool $checkOnline,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminIntegrity
    {
        $now = new \DateTimeImmutable();
        $info = $this->report->manifestInfo();

        $resource = new AdminIntegrity();
        $resource->status = $this->report->effectiveStatus($now);
        $resource->appVersion = $this->appVersion;
        $resource->manifestDigest = $info['digest'];
        $resource->gitSha = $info['gitSha'];
        $resource->checkOnline = $this->checkOnline;
        $resource->checks = array_map(AdminIntegrityCheck::fromEntity(...), $this->report->latestChecks($now));

        return $resource;
    }
}
