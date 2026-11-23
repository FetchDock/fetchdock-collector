<?php

namespace App\State\Ingest;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ReportComment;
use App\RateLimit\IngestRateLimiter;
use App\Repository\ReportRepository;
use App\Resource\Ingest\IngestedReportComment;
use App\Resource\Ingest\UpstreamFailureReportComment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * POST /v1/reports/{id}/comments: appends a comment to one of the calling instance's reports (404 otherwise).
 *
 * @implements ProcessorInterface<UpstreamFailureReportComment, IngestedReportComment>
 */
final readonly class AddCommentProcessor implements ProcessorInterface
{
    public function __construct(
        private CurrentInstance $currentInstance,
        private ReportRepository $reports,
        private EntityManagerInterface $entityManager,
        private IngestRateLimiter $rateLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): IngestedReportComment
    {
        \assert($data instanceof UpstreamFailureReportComment);
        $instance = $this->currentInstance->get();
        $report = $this->reports->findOwned((string) ($uriVariables['id'] ?? ''), $instance)
            ?? throw new NotFoundHttpException('Not Found');
        $this->rateLimiter->consumeComment($instance->getId()->toRfc4122());

        $comment = new ReportComment($report, $data->comment, $data->createdAt);
        $report->setUpdatedAt($comment->getReceivedAt());
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        return new IngestedReportComment($comment->getId());
    }
}
