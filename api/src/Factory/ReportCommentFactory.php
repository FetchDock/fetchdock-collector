<?php

namespace App\Factory;

use App\Entity\ReportComment;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Comments instances added to forwarded reports, for mock data and tests.
 *
 * @extends PersistentObjectFactory<ReportComment>
 */
final class ReportCommentFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return ReportComment::class;
    }

    protected function defaults(): array
    {
        $createdAt = \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-6 months'));

        return [
            'report' => ReportFactory::new(),
            'comment' => self::faker()->sentence(15),
            'createdAt' => $createdAt,
            'receivedAt' => $createdAt->modify('+'.self::faker()->numberBetween(1, 120).' minutes'),
        ];
    }
}
