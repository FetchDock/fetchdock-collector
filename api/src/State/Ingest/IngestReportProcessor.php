<?php

namespace App\State\Ingest;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Report;
use App\RateLimit\IngestRateLimiter;
use App\Resource\Ingest\IngestedReport;
use App\Resource\Ingest\UpstreamFailureReport;
use App\Service\ErrorCategorizer;
use Doctrine\ORM\EntityManagerInterface;

/**
 * POST /v1/reports: stores the payload verbatim, plus the error category derived from diagnostics.errorMessage.
 *
 * @implements ProcessorInterface<UpstreamFailureReport, IngestedReport>
 */
final readonly class IngestReportProcessor implements ProcessorInterface
{
    public function __construct(
        private CurrentInstance $currentInstance,
        private EntityManagerInterface $entityManager,
        private ErrorCategorizer $errorCategorizer,
        private IngestRateLimiter $rateLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): IngestedReport
    {
        \assert($data instanceof UpstreamFailureReport);
        $instance = $this->currentInstance->get();
        $this->rateLimiter->consumeReport($instance->getId()->toRfc4122());

        $report = new Report(
            $instance,
            $data->kind,
            $data->url,
            $data->host,
            $data->serverVersion,
            [
                'policyVersion' => $data->consent->policyVersion,
                'consentedAt' => $data->consent->consentedAt->format(\DATE_ATOM),
            ],
            $data->operatorPolicyVersion,
            $data->createdAt,
        );
        $diagnostics = $data->diagnostics?->toArray();
        $report
            ->setSchemaVersion($data->schemaVersion)
            ->setSite($data->site)
            ->setUserAgent($data->userAgent)
            ->setReferrer($data->referrer)
            ->setDiagnostics([] === $diagnostics ? null : $diagnostics)
            ->setComment($data->comment)
            ->setAdminComment($data->adminComment)
            ->setErrorCategory($this->errorCategorizer->categorize($diagnostics['errorMessage'] ?? null));

        // The instance's version as of its latest report
        $instance->setServerVersion($data->serverVersion);

        $this->entityManager->persist($report);
        $this->entityManager->flush();

        return new IngestedReport($report->getId());
    }
}
