<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250804000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create survey branching tables for AI-powered dynamic survey functionality';
    }

    public function up(Schema $schema): void
    {
        // Survey table
        $this->addSql('CREATE TABLE survey (
            id INT AUTO_INCREMENT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            branching_enabled TINYINT(1) DEFAULT 0 NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY(id),
            INDEX idx_branching_enabled (branching_enabled),
            INDEX idx_created_at (created_at)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // Question table
        $this->addSql('CREATE TABLE question (
            id INT AUTO_INCREMENT NOT NULL,
            survey_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            content LONGTEXT NOT NULL,
            options JSON DEFAULT NULL,
            order_index INT NOT NULL,
            rule_target TINYINT(1) DEFAULT 0 NOT NULL,
            required_field TINYINT(1) DEFAULT 1 NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY(id),
            INDEX IDX_B6F7494EB3FE509D (survey_id),
            INDEX idx_survey_order (survey_id, order_index),
            INDEX idx_rule_target (rule_target),
            CONSTRAINT FK_B6F7494EB3FE509D FOREIGN KEY (survey_id) REFERENCES survey (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // Survey rule table
        $this->addSql('CREATE TABLE survey_rule (
            id INT AUTO_INCREMENT NOT NULL,
            survey_id INT NOT NULL,
            condition_json JSON NOT NULL,
            action_json JSON NOT NULL,
            priority INT NOT NULL,
            active TINYINT(1) DEFAULT 1 NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY(id),
            INDEX IDX_E6F7B0E6B3FE509D (survey_id),
            INDEX idx_survey_priority (survey_id, priority),
            INDEX idx_active (active),
            CONSTRAINT FK_E6F7B0E6B3FE509D FOREIGN KEY (survey_id) REFERENCES survey (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // Survey session table
        $this->addSql('CREATE TABLE survey_session (
            id INT AUTO_INCREMENT NOT NULL,
            survey_id INT NOT NULL,
            current_question_id INT DEFAULT NULL,
            session_token VARCHAR(64) NOT NULL,
            progress_data JSON DEFAULT NULL,
            completed TINYINT(1) DEFAULT 0 NOT NULL,
            created_at DATETIME NOT NULL,
            last_activity DATETIME NOT NULL,
            completed_at DATETIME DEFAULT NULL,
            PRIMARY KEY(id),
            UNIQUE INDEX UNIQ_6D6D3F4A4026DB8F (session_token),
            INDEX IDX_6D6D3F4AB3FE509D (survey_id),
            INDEX IDX_6D6D3F4A73DCB4A0 (current_question_id),
            INDEX idx_session_token (session_token),
            INDEX idx_survey_activity (survey_id, last_activity),
            INDEX idx_completed (completed),
            CONSTRAINT FK_6D6D3F4AB3FE509D FOREIGN KEY (survey_id) REFERENCES survey (id) ON DELETE CASCADE,
            CONSTRAINT FK_6D6D3F4A73DCB4A0 FOREIGN KEY (current_question_id) REFERENCES question (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // Response table
        $this->addSql('CREATE TABLE response (
            id INT AUTO_INCREMENT NOT NULL,
            session_id INT NOT NULL,
            question_id INT NOT NULL,
            value LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY(id),
            INDEX IDX_3E7B0BFB613FECDF (session_id),
            INDEX IDX_3E7B0BFB1E27F6BF (question_id),
            INDEX idx_session_question (session_id, question_id),
            UNIQUE INDEX uniq_session_question (session_id, question_id),
            CONSTRAINT FK_3E7B0BFB613FECDF FOREIGN KEY (session_id) REFERENCES survey_session (id) ON DELETE CASCADE,
            CONSTRAINT FK_3E7B0BFB1E27F6BF FOREIGN KEY (question_id) REFERENCES question (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // Rule dependency table
        $this->addSql('CREATE TABLE rule_dependency (
            id INT AUTO_INCREMENT NOT NULL,
            parent_rule_id INT NOT NULL,
            child_rule_id INT NOT NULL,
            dependency_type VARCHAR(50) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY(id),
            INDEX IDX_8E1D6B91F8697324 (parent_rule_id),
            INDEX IDX_8E1D6B91E2C35FC (child_rule_id),
            INDEX idx_parent_rule (parent_rule_id),
            INDEX idx_child_rule (child_rule_id),
            UNIQUE INDEX uniq_parent_child (parent_rule_id, child_rule_id),
            CONSTRAINT FK_8E1D6B91F8697324 FOREIGN KEY (parent_rule_id) REFERENCES survey_rule (id) ON DELETE CASCADE,
            CONSTRAINT FK_8E1D6B91E2C35FC FOREIGN KEY (child_rule_id) REFERENCES survey_rule (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // Response audit table
        $this->addSql('CREATE TABLE response_audit (
            id INT AUTO_INCREMENT NOT NULL,
            response_id INT NOT NULL,
            rule_id INT NOT NULL,
            evaluation_result JSON NOT NULL,
            timestamp DATETIME NOT NULL,
            notes LONGTEXT DEFAULT NULL,
            PRIMARY KEY(id),
            INDEX IDX_7D4F9CFBFBF32840 (response_id),
            INDEX IDX_7D4F9CFB744E0351 (rule_id),
            INDEX idx_response_timestamp (response_id, timestamp),
            INDEX idx_rule_id (rule_id),
            CONSTRAINT FK_7D4F9CFBFBF32840 FOREIGN KEY (response_id) REFERENCES response (id) ON DELETE CASCADE,
            CONSTRAINT FK_7D4F9CFB744E0351 FOREIGN KEY (rule_id) REFERENCES survey_rule (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE response_audit');
        $this->addSql('DROP TABLE rule_dependency');
        $this->addSql('DROP TABLE response'); 
        $this->addSql('DROP TABLE survey_session');
        $this->addSql('DROP TABLE survey_rule');
        $this->addSql('DROP TABLE question');
        $this->addSql('DROP TABLE survey');
    }
}