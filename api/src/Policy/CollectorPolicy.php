<?php

namespace App\Policy;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The collector's privacy policy versions (collector.policy.* in config/packages/collector.yaml). Instances register,
 * and reports are accepted, only under an accepted version.
 */
final readonly class CollectorPolicy
{
    /**
     * @param list<string> $acceptedVersions
     */
    public function __construct(
        #[Autowire(param: 'collector.policy.current_version')]
        public string $currentVersion,
        #[Autowire(param: 'collector.policy.accepted_versions')]
        public array $acceptedVersions,
    ) {
    }

    public function isAccepted(?string $version): bool
    {
        return null !== $version && \in_array($version, $this->acceptedVersions, true);
    }
}
