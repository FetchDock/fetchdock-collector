<?php

namespace App\Service\Integrity;

use App\Entity\IntegrityCheck;
use App\Enum\IntegrityStatus;
use App\Repository\IntegrityCheckRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The collector's integrity status (ADR 0013, collector#5): the worst check of the last 48 hours across all api and
 * worker containers (unknown when there is none), the image's manifest digest and commit, and the latest check of each
 * container. Containers check on start and the worker daily.
 */
class IntegrityReport
{
    public const string WINDOW = '-48 hours';

    /** @var array{digest: ?string, gitSha: ?string}|null */
    private ?array $manifestInfo = null;

    public function __construct(
        private readonly IntegrityCheckRepository $repository,
        private readonly IntegrityManifest $manifest,
        #[Autowire('%app.integrity.trust_dev%')]
        private readonly bool $trustDev = false,
    ) {
    }

    public function effectiveStatus(\DateTimeImmutable $now = new \DateTimeImmutable()): IntegrityStatus
    {
        if ($this->trustDev) {
            return IntegrityStatus::VERIFIED;
        }

        return IntegrityStatus::worst(...array_map(
            static fn (IntegrityCheck $check): IntegrityStatus => $check->getStatus(),
            $this->repository->findSince($now->modify(self::WINDOW)),
        ));
    }

    /**
     * The latest check of each api and worker container within the status window, newest first.
     *
     * @return list<IntegrityCheck>
     */
    public function latestChecks(\DateTimeImmutable $now = new \DateTimeImmutable()): array
    {
        return $this->repository->findLatestPerContainerSince($now->modify(self::WINDOW));
    }

    /**
     * The digest and commit of this container's manifest, null without one (development).
     *
     * @return array{digest: ?string, gitSha: ?string}
     */
    public function manifestInfo(): array
    {
        if (null === $this->manifestInfo) {
            $content = is_file($this->manifest->path()) ? file_get_contents($this->manifest->path()) : false;
            $document = false === $content ? null : json_decode($content, true);
            $gitSha = \is_array($document) && \is_string($document['gitSha'] ?? null) && preg_match('/^[0-9a-f]{7,40}$/', $document['gitSha']) ? $document['gitSha'] : null;
            $this->manifestInfo = [
                'digest' => false === $content ? null : hash('sha256', $content),
                'gitSha' => $gitSha,
            ];
        }

        return $this->manifestInfo;
    }
}
