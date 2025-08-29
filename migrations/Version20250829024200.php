<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Survey Gamification System - Initial Migration
 */
final class Version20250829024200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tables for survey gamification system: users, surveys, responses, achievements, points, and streaks';
    }

    public function up(Schema $schema): void
    {
        // Create user table
        $this->addSql('CREATE TABLE user (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            email VARCHAR(180) NOT NULL,
            roles CLOB NOT NULL,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            last_active_at DATETIME DEFAULT NULL,
            total_points INTEGER NOT NULL DEFAULT 0,
            timezone VARCHAR(100) DEFAULT NULL,
            CONSTRAINT UNIQ_IDENTIFIER_EMAIL UNIQUE (email)
        )');

        // Create survey table
        $this->addSql('CREATE TABLE survey (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description CLOB DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            is_active BOOLEAN NOT NULL DEFAULT 1,
            starts_at DATETIME DEFAULT NULL,
            ends_at DATETIME DEFAULT NULL,
            base_points INTEGER NOT NULL DEFAULT 10,
            quality_multiplier INTEGER NOT NULL DEFAULT 2,
            minimum_time_seconds INTEGER DEFAULT NULL,
            gamification_enabled BOOLEAN NOT NULL DEFAULT 1,
            form_fields CLOB DEFAULT NULL
        )');

        // Create survey_response table
        $this->addSql('CREATE TABLE survey_response (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            user_id INTEGER NOT NULL,
            survey_id INTEGER NOT NULL,
            response_data CLOB NOT NULL,
            started_at DATETIME NOT NULL,
            completed_at DATETIME DEFAULT NULL,
            is_completed BOOLEAN NOT NULL DEFAULT 0,
            time_spent_seconds INTEGER DEFAULT NULL,
            completion_percentage REAL NOT NULL DEFAULT 0.0,
            points_earned INTEGER NOT NULL DEFAULT 0,
            quality_score REAL DEFAULT NULL,
            ip_address VARCHAR(255) DEFAULT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            CONSTRAINT FK_survey_response_user FOREIGN KEY (user_id) REFERENCES user (id),
            CONSTRAINT FK_survey_response_survey FOREIGN KEY (survey_id) REFERENCES survey (id)
        )');

        // Create achievement_definition table
        $this->addSql('CREATE TABLE achievement_definition (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description CLOB NOT NULL,
            icon VARCHAR(255) NOT NULL,
            points INTEGER NOT NULL DEFAULT 0,
            type VARCHAR(50) NOT NULL,
            criteria CLOB NOT NULL,
            is_active BOOLEAN NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL
        )');

        // Create user_achievement table
        $this->addSql('CREATE TABLE user_achievement (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            user_id INTEGER NOT NULL,
            achievement_id INTEGER NOT NULL,
            unlocked_at DATETIME NOT NULL,
            points_awarded INTEGER NOT NULL DEFAULT 0,
            is_notified BOOLEAN NOT NULL DEFAULT 0,
            context VARCHAR(500) DEFAULT NULL,
            CONSTRAINT FK_user_achievement_user FOREIGN KEY (user_id) REFERENCES user (id),
            CONSTRAINT FK_user_achievement_achievement FOREIGN KEY (achievement_id) REFERENCES achievement_definition (id),
            CONSTRAINT user_achievement_unique UNIQUE (user_id, achievement_id)
        )');

        // Create user_point table
        $this->addSql('CREATE TABLE user_point (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            user_id INTEGER NOT NULL,
            survey_id INTEGER DEFAULT NULL,
            points INTEGER NOT NULL DEFAULT 0,
            reason VARCHAR(100) NOT NULL,
            context VARCHAR(500) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            CONSTRAINT FK_user_point_user FOREIGN KEY (user_id) REFERENCES user (id),
            CONSTRAINT FK_user_point_survey FOREIGN KEY (survey_id) REFERENCES survey (id)
        )');

        // Create user_streak table
        $this->addSql('CREATE TABLE user_streak (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            user_id INTEGER NOT NULL,
            current_streak INTEGER NOT NULL DEFAULT 0,
            longest_streak INTEGER NOT NULL DEFAULT 0,
            last_activity_at DATETIME DEFAULT NULL,
            timezone VARCHAR(100) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            streak_started_at DATETIME DEFAULT NULL,
            CONSTRAINT FK_user_streak_user FOREIGN KEY (user_id) REFERENCES user (id),
            CONSTRAINT UNIQ_user_streak_user UNIQUE (user_id)
        )');

        // Create indexes
        $this->addSql('CREATE INDEX IDX_survey_response_user ON survey_response (user_id)');
        $this->addSql('CREATE INDEX IDX_survey_response_survey ON survey_response (survey_id)');
        $this->addSql('CREATE INDEX IDX_survey_response_completed ON survey_response (is_completed)');
        $this->addSql('CREATE INDEX IDX_user_achievement_user ON user_achievement (user_id)');
        $this->addSql('CREATE INDEX IDX_user_achievement_achievement ON user_achievement (achievement_id)');
        $this->addSql('CREATE INDEX IDX_user_point_user ON user_point (user_id)');
        $this->addSql('CREATE INDEX IDX_user_point_survey ON user_point (survey_id)');
        $this->addSql('CREATE INDEX IDX_user_point_created ON user_point (created_at)');
        $this->addSql('CREATE INDEX IDX_user_streak_user ON user_streak (user_id)');
        $this->addSql('CREATE INDEX IDX_user_total_points ON user (total_points)');
        $this->addSql('CREATE INDEX IDX_survey_active ON survey (is_active)');
        $this->addSql('CREATE INDEX IDX_survey_gamification ON survey (gamification_enabled)');
    }

    public function down(Schema $schema): void
    {
        // Drop tables in reverse order to respect foreign key constraints
        $this->addSql('DROP TABLE user_streak');
        $this->addSql('DROP TABLE user_point');
        $this->addSql('DROP TABLE user_achievement');
        $this->addSql('DROP TABLE achievement_definition');
        $this->addSql('DROP TABLE survey_response');
        $this->addSql('DROP TABLE survey');
        $this->addSql('DROP TABLE user');
    }
}