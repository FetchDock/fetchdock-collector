<?php

namespace App\Serializer;

use App\Entity\Report;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * AdminReport.instance is for ROLE_ADMIN only: other maintainers get null, so they can't tell instances apart.
 */
final class AdminReportNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const string ALREADY_CALLED = 'admin_report_normalizer_already_called';

    public function __construct(private readonly Security $security)
    {
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::ALREADY_CALLED] = true;
        $normalized = $this->normalizer->normalize($data, $format, $context);

        if (\is_array($normalized) && \array_key_exists('instance', $normalized) && !$this->security->isGranted('ROLE_ADMIN')) {
            $normalized['instance'] = null;
        }

        return $normalized;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Report && !isset($context[self::ALREADY_CALLED]);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Report::class => false];
    }
}
