<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261123205839 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Report: downloader (from diagnostics) for filters and stats; indexes on error_category and downloader';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE report ADD downloader VARCHAR(255) DEFAULT NULL');
        $this->addSql("UPDATE report SET downloader = LEFT(diagnostics->>'downloader', 255) WHERE diagnostics->>'downloader' <> ''");
        $this->addSql('CREATE INDEX IDX_C42F778434093173 ON report (error_category)');
        $this->addSql('CREATE INDEX IDX_C42F7784CBD17EED ON report (downloader)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_C42F778434093173');
        $this->addSql('DROP INDEX IDX_C42F7784CBD17EED');
        $this->addSql('ALTER TABLE report DROP downloader');
    }
}
