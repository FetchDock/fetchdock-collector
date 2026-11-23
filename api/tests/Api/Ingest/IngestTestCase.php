<?php

namespace App\Tests\Api\Ingest;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Helpers for the ingest API (/v1) tests: JSON requests with an instance secret as bearer token.
 */
abstract class IngestTestCase extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    protected static ?bool $alwaysBootKernel = false;

    /** Leaves the field out of reportPayload() */
    protected const string MISSING = "\0missing";

    /**
     * @param array<string, mixed>|null $json
     * @param array<string, mixed>      $options
     */
    protected static function ingest(string $method, string $path, ?string $secret = null, ?array $json = null, array $options = []): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        $headers = ['Accept' => 'application/json'];
        if (null !== $secret) {
            $headers['Authorization'] = 'Bearer '.$secret;
        }
        if (null !== $json) {
            $options['json'] = $json;
            $headers['Content-Type'] = 'application/json';
        }

        return static::client()->request($method, $path, ['headers' => $headers] + $options);
    }

    protected static function client(): Client
    {
        return static::createClient();
    }

    /**
     * A valid UpstreamFailureReport (schemaVersion 1).
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    protected static function reportPayload(array $overrides = []): array
    {
        return array_filter(array_replace([
            'schemaVersion' => 1,
            'serverVersion' => '0.8.0',
            'kind' => 'job_failed',
            'url' => 'https://www.youtube.com/watch?v=abcdefghijk',
            'host' => 'www.youtube.com',
            'site' => 'youtube',
            'userAgent' => 'Mozilla/5.0',
            'referrer' => null,
            'diagnostics' => [
                'downloader' => 'yt-dlp-cli',
                'downloaderVersion' => '2026.11.11',
                'errorMessage' => 'ERROR: unable to download video data: HTTP Error 403: Forbidden',
                'clientVersion' => '0.8.0',
            ],
            'comment' => 'Fails every time',
            'adminComment' => null,
            'createdAt' => '2026-11-20T10:00:00+00:00',
            'consent' => ['policyVersion' => '1', 'consentedAt' => '2026-11-20T09:59:00+00:00'],
            'operatorPolicyVersion' => '1',
        ], $overrides), static fn (mixed $value): bool => self::MISSING !== $value);
    }
}
