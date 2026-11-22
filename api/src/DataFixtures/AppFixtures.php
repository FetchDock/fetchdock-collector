<?php

namespace App\DataFixtures;

use App\Factory\InstanceFactory;
use App\Factory\ReportCommentFactory;
use App\Factory\ReportFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Mock data for development and staging (ADR 0014): about 20 instances (a few blocked or retired), 500 reports and
 * comments on some of them. Load it with app:fixtures:seed, never on production.
 */
final class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $instances = [
            ...InstanceFactory::createMany(17),
            ...InstanceFactory::new()->blocked()->many(1)->create(),
            ...InstanceFactory::new()->retired()->many(2)->create(),
        ];

        $reports = ReportFactory::createMany(500, static fn () => [
            'instance' => $instances[array_rand($instances)],
        ]);

        foreach (array_rand($reports, 60) as $index) {
            ReportCommentFactory::createOne(['report' => $reports[$index]]);
        }
    }
}
