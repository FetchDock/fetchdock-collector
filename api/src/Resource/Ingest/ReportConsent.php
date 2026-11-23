<?php

namespace App\Resource\Ingest;

use App\Validator\AcceptedPolicyVersion;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The reporting user's consent to forward the report.
 */
final class ReportConsent
{
    /** The collector policy version shown to the user */
    #[Assert\NotBlank]
    #[AcceptedPolicyVersion]
    public string $policyVersion;

    #[Assert\NotNull]
    public \DateTimeImmutable $consentedAt;
}
