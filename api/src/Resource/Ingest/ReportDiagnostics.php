<?php

namespace App\Resource\Ingest;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Diagnostics of a failed job, each field opt-in by the user (job_failed reports only).
 */
final class ReportDiagnostics
{
    /** e.g. yt-dlp-cli */
    #[Assert\Length(max: 255)]
    public ?string $downloader = null;

    #[Assert\Length(max: 255)]
    public ?string $downloaderVersion = null;

    /** Verbatim from the job's events */
    #[Assert\Length(max: 4000)]
    public ?string $errorMessage = null;

    /** Version of the reporting client */
    #[Assert\Length(max: 255)]
    public ?string $clientVersion = null;

    /** @return array<string, string> the fields that were sent */
    public function toArray(): array
    {
        return array_filter([
            'downloader' => $this->downloader,
            'downloaderVersion' => $this->downloaderVersion,
            'errorMessage' => $this->errorMessage,
            'clientVersion' => $this->clientVersion,
        ], static fn (?string $value): bool => null !== $value);
    }
}
