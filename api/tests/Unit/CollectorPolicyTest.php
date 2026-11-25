<?php

namespace App\Tests\Unit;

use App\Policy\CollectorPolicy;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * config/packages/collector.yaml and policy/*.md stay in step: /policy and /policy/{version} render every published
 * version.
 */
final class CollectorPolicyTest extends KernelTestCase
{
    public function testEveryPublishedVersionHasItsText(): void
    {
        $policy = self::getContainer()->get(CollectorPolicy::class);
        self::assertInstanceOf(CollectorPolicy::class, $policy);

        foreach ([$policy->currentVersion, ...$policy->acceptedVersions] as $version) {
            $text = $policy->text($version);
            self::assertNotNull($text, \sprintf('policy/%s.md is missing', $version));
            self::assertStringContainsString(\sprintf('**Version:** %s ', $version), $text, \sprintf('policy/%s.md states another version', $version));
        }
    }

    public function testOtherVersionsHaveNoText(): void
    {
        $policy = new CollectorPolicy('2', ['1', '2'], \dirname(__DIR__, 2).'/policy');

        self::assertNull($policy->text('0'));
        self::assertNull($policy->text('../composer'));
    }
}
