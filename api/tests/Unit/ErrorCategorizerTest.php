<?php

namespace App\Tests\Unit;

use App\Service\ErrorCategorizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The rules in config/packages/collector.yaml.
 */
final class ErrorCategorizerTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function messages(): iterable
    {
        yield 'no message' => [null, null];
        yield 'empty' => ['  ', null];
        yield '403' => ['ERROR: unable to download video data: HTTP Error 403: Forbidden', 'http_403'];
        yield '429' => ['ERROR: unable to download webpage: HTTP Error 429: Too Many Requests', 'http_429'];
        yield 'geo' => ['ERROR: [vimeo] 1: The uploader has not made this video available in your country', 'geo_blocked'];
        yield 'login' => ['ERROR: [youtube] x: Sign in to confirm you\'re not a bot.', 'login_required'];
        yield 'unsupported' => ['ERROR: Unsupported URL: https://example.net/v/1', 'unsupported_url'];
        yield 'extractor' => ['[gallery-dl][error] KeyError: \'media\'', 'extractor_error'];
        yield 'other' => ['ERROR: Postprocessing: ffprobe and ffmpeg not found', 'other'];
    }

    #[DataProvider('messages')]
    public function testCategorize(?string $message, ?string $category): void
    {
        self::assertSame($category, self::getContainer()->get(ErrorCategorizer::class)->categorize($message));
    }
}
