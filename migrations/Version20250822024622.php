<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250822024622 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial migration: Create surveys, questions, and ai_suggestions tables for AI-powered survey builder';
    }

    public function up(Schema $schema): void
    {
        // Create surveys table
        $this->addSql('CREATE TABLE surveys (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            objective TEXT DEFAULT NULL,
            target_audience VARCHAR(100) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            is_published BOOLEAN NOT NULL DEFAULT 0
        )');

        // Create questions table
        $this->addSql('CREATE TABLE questions (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            survey_id INTEGER NOT NULL,
            text TEXT NOT NULL,
            type VARCHAR(50) NOT NULL DEFAULT \'text\',
            options JSON DEFAULT NULL,
            position INTEGER NOT NULL DEFAULT 0,
            is_required BOOLEAN NOT NULL DEFAULT 1,
            help_text TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            is_ai_generated BOOLEAN NOT NULL DEFAULT 0,
            ai_confidence_score DECIMAL(3,2) DEFAULT NULL,
            FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
        )');

        // Create ai_suggestions table
        $this->addSql('CREATE TABLE ai_suggestions (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            survey_id INTEGER NOT NULL,
            suggestion_type VARCHAR(50) NOT NULL DEFAULT \'question\',
            original_content TEXT DEFAULT NULL,
            suggested_content TEXT NOT NULL,
            confidence_score DECIMAL(3,2) NOT NULL DEFAULT 0.00,
            rationale TEXT DEFAULT NULL,
            user_action VARCHAR(20) NOT NULL DEFAULT \'pending\',
            metadata JSON DEFAULT NULL,
            created_at DATETIME NOT NULL,
            applied_at DATETIME DEFAULT NULL,
            user_rating INTEGER DEFAULT NULL,
            user_feedback TEXT DEFAULT NULL,
            FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
        )');

        // Create indexes for performance
        $this->addSql('CREATE INDEX idx_questions_survey_position ON questions (survey_id, position)');
        $this->addSql('CREATE INDEX idx_ai_suggestions_survey_type ON ai_suggestions (survey_id, suggestion_type)');
        $this->addSql('CREATE INDEX idx_ai_suggestions_created_at ON ai_suggestions (created_at)');
        $this->addSql('CREATE INDEX idx_questions_survey_id ON questions (survey_id)');
        $this->addSql('CREATE INDEX idx_ai_suggestions_survey_id ON ai_suggestions (survey_id)');
    }

    public function down(Schema $schema): void
    {
        // Drop tables in reverse order (respecting foreign key constraints)
        $this->addSql('DROP TABLE ai_suggestions');
        $this->addSql('DROP TABLE questions');
        $this->addSql('DROP TABLE surveys');
    }
}