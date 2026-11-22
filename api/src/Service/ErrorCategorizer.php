<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Derives a report's error category from diagnostics.errorMessage with the ordered rules in
 * collector.error_categories (config/packages/collector.yaml): the first matching rule wins.
 */
final readonly class ErrorCategorizer
{
    public const string OTHER = 'other';

    /**
     * @param list<array{category: string, pattern: string}> $rules
     */
    public function __construct(
        #[Autowire(param: 'collector.error_categories')]
        private array $rules,
    ) {
    }

    /**
     * The category, "other" if no rule matches, null for reports without an error message.
     */
    public function categorize(?string $errorMessage): ?string
    {
        if (null === $errorMessage || '' === trim($errorMessage)) {
            return null;
        }

        foreach ($this->rules as $rule) {
            if (1 === preg_match($rule['pattern'], $errorMessage)) {
                return $rule['category'];
            }
        }

        return self::OTHER;
    }
}
