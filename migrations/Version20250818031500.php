<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250818031500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create initial survey system tables for AI-powered survey builder';
    }

    public function up(Schema $schema): void
    {
        // Create user table
        $this->addSql('CREATE TABLE `user` (
            id INT AUTO_INCREMENT NOT NULL, 
            email VARCHAR(180) NOT NULL UNIQUE, 
            name VARCHAR(255) NOT NULL, 
            created_at DATETIME NOT NULL, 
            role VARCHAR(50) NOT NULL DEFAULT "user", 
            PRIMARY KEY(id),
            UNIQUE INDEX UNIQ_USER_EMAIL (email)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create survey table
        $this->addSql('CREATE TABLE survey (
            id INT AUTO_INCREMENT NOT NULL, 
            creator_id INT NOT NULL, 
            title VARCHAR(255) NOT NULL, 
            description LONGTEXT, 
            created_at DATETIME NOT NULL, 
            updated_at DATETIME NOT NULL, 
            status VARCHAR(50) NOT NULL DEFAULT "draft", 
            ai_predicted_completion_rate DOUBLE PRECISION DEFAULT NULL, 
            ai_metadata JSON DEFAULT NULL, 
            PRIMARY KEY(id),
            INDEX IDX_SURVEY_CREATOR (creator_id),
            CONSTRAINT FK_SURVEY_CREATOR FOREIGN KEY (creator_id) REFERENCES `user` (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create question table
        $this->addSql('CREATE TABLE question (
            id INT AUTO_INCREMENT NOT NULL, 
            survey_id INT NOT NULL, 
            text LONGTEXT NOT NULL, 
            type VARCHAR(50) NOT NULL DEFAULT "text", 
            position INT NOT NULL DEFAULT 0, 
            required TINYINT(1) NOT NULL DEFAULT 0, 
            options JSON DEFAULT NULL, 
            created_at DATETIME NOT NULL, 
            updated_at DATETIME NOT NULL, 
            ai_analysis JSON DEFAULT NULL, 
            ai_bias_score DOUBLE PRECISION DEFAULT NULL, 
            ai_suggestions JSON DEFAULT NULL, 
            PRIMARY KEY(id),
            INDEX IDX_QUESTION_SURVEY (survey_id),
            INDEX IDX_QUESTION_POSITION (survey_id, position),
            CONSTRAINT FK_QUESTION_SURVEY FOREIGN KEY (survey_id) REFERENCES survey (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create response table
        $this->addSql('CREATE TABLE response (
            id INT AUTO_INCREMENT NOT NULL, 
            survey_id INT NOT NULL, 
            question_id INT NOT NULL, 
            value LONGTEXT, 
            submitted_at DATETIME NOT NULL, 
            ip_address VARCHAR(45), 
            user_agent VARCHAR(500), 
            session_id VARCHAR(255), 
            metadata JSON DEFAULT NULL, 
            PRIMARY KEY(id),
            INDEX IDX_RESPONSE_SURVEY (survey_id),
            INDEX IDX_RESPONSE_QUESTION (question_id),
            INDEX IDX_RESPONSE_SESSION (session_id),
            INDEX IDX_RESPONSE_SUBMITTED (submitted_at),
            CONSTRAINT FK_RESPONSE_SURVEY FOREIGN KEY (survey_id) REFERENCES survey (id) ON DELETE CASCADE,
            CONSTRAINT FK_RESPONSE_QUESTION FOREIGN KEY (question_id) REFERENCES question (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS response');
        $this->addSql('DROP TABLE IF EXISTS question');
        $this->addSql('DROP TABLE IF EXISTS survey');
        $this->addSql('DROP TABLE IF EXISTS `user`');
    }
}