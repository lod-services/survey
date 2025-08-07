<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250807031435_CreateTemplateAndSurveyTables extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create template and survey tables for Smart Survey Template Ecosystem';
    }

    public function up(Schema $schema): void
    {
        // Create templates table
        $this->addSql('CREATE TABLE templates (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            industry VARCHAR(100) NOT NULL,
            category VARCHAR(100) NOT NULL,
            structure JSON NOT NULL,
            metadata JSON DEFAULT NULL,
            status VARCHAR(20) DEFAULT "active" NOT NULL,
            compliance_flags JSON DEFAULT NULL,
            usage_count INT DEFAULT 0,
            average_rating DOUBLE PRECISION DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            updated_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            version VARCHAR(50) NOT NULL,
            tags JSON DEFAULT NULL,
            PRIMARY KEY(id),
            INDEX idx_template_industry (industry),
            INDEX idx_template_status (status)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create surveys table
        $this->addSql('CREATE TABLE surveys (
            id INT AUTO_INCREMENT NOT NULL,
            template_id INT DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            structure JSON NOT NULL,
            status VARCHAR(20) DEFAULT "draft" NOT NULL,
            settings JSON DEFAULT NULL,
            response_count INT DEFAULT 0,
            created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            updated_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            published_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)",
            closed_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)",
            PRIMARY KEY(id),
            INDEX IDX_FC59C2B35DA0FB8 (template_id),
            INDEX idx_survey_status (status)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Add foreign key constraint
        $this->addSql('ALTER TABLE surveys ADD CONSTRAINT FK_FC59C2B35DA0FB8 FOREIGN KEY (template_id) REFERENCES templates (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE surveys DROP FOREIGN KEY FK_FC59C2B35DA0FB8');
        $this->addSql('DROP TABLE surveys');
        $this->addSql('DROP TABLE templates');
    }
}