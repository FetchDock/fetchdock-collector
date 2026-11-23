<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Resource\CollectorIntegrity;
use App\Service\Integrity\IntegrityReport;
use App\Service\Integrity\OfficialDigestProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * GET /v1/integrity.
 *
 * @implements ProviderInterface<CollectorIntegrity>
 */
final readonly class CollectorIntegrityProvider implements ProviderInterface
{
    public function __construct(
        private IntegrityReport $report,
        #[Autowire('%app.version%')]
        private string $appVersion,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CollectorIntegrity
    {
        $now = new \DateTimeImmutable();
        $info = $this->report->manifestInfo();
        $checks = $this->report->latestChecks($now);

        $integrity = new CollectorIntegrity();
        $integrity->status = $this->report->effectiveStatus($now);
        $integrity->appVersion = $this->appVersion;
        $integrity->gitSha = $info['gitSha'];
        $integrity->manifestDigest = $info['digest'];
        $integrity->digestUrl = OfficialDigestProvider::url($this->appVersion);
        $integrity->checkedAt = [] === $checks ? null : $checks[0]->getCheckedAt();

        return $integrity;
    }
}
