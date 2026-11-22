<?php

namespace App\Factory;

use App\Entity\Report;
use App\Enum\ReportKind;
use App\Enum\ReportStatus;
use App\Service\ErrorCategorizer;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Forwarded failure reports with realistic hosts, downloaders and error messages, for mock data and tests.
 *
 * @extends PersistentObjectFactory<Report>
 */
final class ReportFactory extends PersistentObjectFactory
{
    /** host => site slug (null: unknown to the instance) and a URL path pattern */
    private const array SITES = [
        'www.youtube.com' => ['youtube', '/watch?v=%s'],
        'youtu.be' => ['youtube', '/%s'],
        'vimeo.com' => ['vimeo', '/%d'],
        'www.instagram.com' => ['instagram', '/p/%s/'],
        'x.com' => ['twitter', '/someone/status/%d'],
        'www.reddit.com' => ['reddit', '/r/videos/comments/%s/'],
        'soundcloud.com' => ['soundcloud', '/artist/%s'],
        'www.twitch.tv' => ['twitch', '/videos/%d'],
        'www.tiktok.com' => ['tiktok', '/@someone/video/%d'],
        'www.deviantart.com' => ['deviantart', '/artist/art/%s'],
        'example-video-host.net' => [null, '/v/%s'],
        'cdn.unknown-media.org' => [null, '/media/%s.mp4'],
    ];

    private const array ERRORS = [
        'ERROR: [youtube] %s: Sign in to confirm you\'re not a bot. Use --cookies-from-browser or --cookies for the authentication.',
        'ERROR: unable to download video data: HTTP Error 403: Forbidden',
        'ERROR: unable to download webpage: HTTP Error 429: Too Many Requests',
        'ERROR: [vimeo] %s: The uploader has not made this video available in your country',
        'ERROR: [instagram] %s: Requested content is not available, rate-limit reached or login required',
        'ERROR: Unsupported URL: https://example-video-host.net/v/%s',
        'ERROR: [generic] Unable to extract JS player URL; please report this issue',
        '[gallery-dl][error] No suitable extractor found for \'%s\'',
        '[gallery-dl][error] KeyError: \'media\'',
        'ERROR: Postprocessing: ffprobe and ffmpeg not found',
        'ERROR: [twitch:vod] %s: Connection reset by peer',
    ];

    public function __construct(private readonly ErrorCategorizer $errorCategorizer)
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return Report::class;
    }

    public function withStatus(ReportStatus $status): self
    {
        return $this->with(['status' => $status]);
    }

    protected function defaults(): array
    {
        $faker = self::faker();
        $host = $faker->randomElement(array_keys(self::SITES));
        [$site, $path] = self::SITES[$host];
        $url = 'https://'.$host.\sprintf($path, str_contains($path, '%d') ? $faker->randomNumber(8) : $faker->regexify('[A-Za-z0-9_-]{11}'));
        $kind = null === $site && $faker->boolean(60) ? ReportKind::UnsupportedUrl : $faker->randomElement(ReportKind::cases());
        $reportedAt = \DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-12 months'));
        $receivedAt = $reportedAt->modify('+'.$faker->numberBetween(5, 3 * 24 * 60).' minutes');
        $downloader = $faker->randomElement(['yt-dlp-cli', 'gallery-dl-cli']);

        return [
            'instance' => InstanceFactory::new(),
            'kind' => $kind,
            'url' => $url,
            'host' => $host,
            'site' => $site,
            'serverVersion' => $faker->randomElement(['0.1.0', '0.1.1', '0.1.2', '0.2.0']),
            'userAgent' => $faker->boolean(80) ? $faker->userAgent() : null,
            'referrer' => $faker->boolean(30) ? 'https://'.$host.'/' : null,
            'diagnostics' => ReportKind::JobFailed === $kind ? [
                'downloader' => $downloader,
                'downloaderVersion' => 'yt-dlp-cli' === $downloader ? $faker->randomElement(['2026.09.27', '2026.10.22', '2026.11.11']) : $faker->randomElement(['1.30.9', '1.31.0']),
                'errorMessage' => \sprintf($faker->randomElement(self::ERRORS), $faker->regexify('[A-Za-z0-9_-]{11}')),
                'clientVersion' => $faker->randomElement(['0.1.0', '0.1.2', '0.2.0']),
            ] : null,
            'comment' => $faker->boolean(40) ? $faker->sentence(12) : null,
            'adminComment' => $faker->boolean(15) ? $faker->sentence(8) : null,
            'consent' => ['policyVersion' => '1', 'consentedAt' => $reportedAt->modify('-1 minute')->format(\DATE_ATOM)],
            'operatorPolicyVersion' => '1',
            'reportedAt' => $reportedAt,
            'receivedAt' => $receivedAt,
            'updatedAt' => $receivedAt,
            'status' => $faker->randomElement([ReportStatus::New, ReportStatus::New, ReportStatus::New, ReportStatus::Acknowledged, ReportStatus::Resolved, ReportStatus::WontFix, ReportStatus::Duplicate]),
            'maintainerNote' => $faker->boolean(20) ? $faker->sentence(10) : null,
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(function (Report $report): void {
            $report->setErrorCategory($this->errorCategorizer->categorize($report->getDiagnostics()['errorMessage'] ?? null));
        });
    }
}
