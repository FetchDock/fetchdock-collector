<?php

namespace App\Policy;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The collector's privacy policy versions (collector.policy.* in config/packages/collector.yaml), whose texts are
 * policy/<version>.md. Instances register, and reports are accepted, only under an accepted version.
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
        #[Autowire('%kernel.project_dir%/policy')]
        private string $textDirectory,
    ) {
    }

    public function isAccepted(?string $version): bool
    {
        return null !== $version && \in_array($version, $this->acceptedVersions, true);
    }

    /**
     * The Markdown text of a published version: the current one or one still accepted. Null for any other version,
     * so withdrawn texts and arbitrary file names are never served.
     */
    public function text(string $version): ?string
    {
        if ($version !== $this->currentVersion && !$this->isAccepted($version)) {
            return null;
        }

        $text = @file_get_contents($this->textDirectory.'/'.$version.'.md');

        return false === $text ? null : $text;
    }
}
