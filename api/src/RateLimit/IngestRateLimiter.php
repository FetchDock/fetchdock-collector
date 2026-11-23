<?php

namespace App\RateLimit;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * The optional abuse limits of the ingest API (config/packages/rate_limiter.yaml). A limit of 0 (the default) is off.
 */
final readonly class IngestRateLimiter
{
    public function __construct(
        private RateLimiterFactoryInterface $instanceRegistrationLimiter,
        private RateLimiterFactoryInterface $instanceReportsLimiter,
        private RateLimiterFactoryInterface $instanceCommentsLimiter,
        #[Autowire(param: 'app.rate_limit.registration_per_ip')]
        private int $registrationPerIp,
        #[Autowire(param: 'app.rate_limit.reports_per_instance')]
        private int $reportsPerInstance,
        #[Autowire(param: 'app.rate_limit.comments_per_instance')]
        private int $commentsPerInstance,
    ) {
    }

    /** POST /v1/instances, per client IP (kept only in the limiter's cache) */
    public function consumeRegistration(?string $clientIp): void
    {
        $this->consume($this->instanceRegistrationLimiter, $this->registrationPerIp, $clientIp ?? 'unknown');
    }

    /** POST /v1/reports, per instance */
    public function consumeReport(string $instanceId): void
    {
        $this->consume($this->instanceReportsLimiter, $this->reportsPerInstance, $instanceId);
    }

    /** POST /v1/reports/{id}/comments, per instance */
    public function consumeComment(string $instanceId): void
    {
        $this->consume($this->instanceCommentsLimiter, $this->commentsPerInstance, $instanceId);
    }

    private function consume(RateLimiterFactoryInterface $factory, int $limit, string $key): void
    {
        if ($limit <= 0) {
            return;
        }

        $rateLimit = $factory->create($key)->consume();
        if (!$rateLimit->isAccepted()) {
            $retryAfter = max(1, $rateLimit->getRetryAfter()->getTimestamp() - time());

            throw new TooManyRequestsHttpException($retryAfter, 'Rate limit exceeded, retry later.');
        }
    }
}
