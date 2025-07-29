<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial migration for authentication system
 */
final class Version20250729000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users, login_attempts, and audit_log tables for authentication system';
    }

    public function up(Schema $schema): void
    {
        // Create users table
        $this->addSql('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(180) NOT NULL UNIQUE,
            email VARCHAR(255) NOT NULL UNIQUE,
            roles JSON NOT NULL,
            password VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            last_login DATETIME,
            is_active BOOLEAN NOT NULL DEFAULT 1,
            is_verified BOOLEAN NOT NULL DEFAULT 0,
            verification_token VARCHAR(255),
            reset_password_token VARCHAR(255),
            reset_password_token_expires_at DATETIME
        )');

        // Create login_attempts table
        $this->addSql('CREATE TABLE login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_address VARCHAR(45) NOT NULL,
            username VARCHAR(180),
            attempted_at DATETIME NOT NULL,
            successful BOOLEAN NOT NULL DEFAULT 0,
            user_agent VARCHAR(255),
            failure_reason VARCHAR(255)
        )');

        // Create audit_log table
        $this->addSql('CREATE TABLE audit_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action VARCHAR(100) NOT NULL,
            resource VARCHAR(255),
            ip_address VARCHAR(45),
            timestamp DATETIME NOT NULL,
            details JSON,
            user_agent VARCHAR(255),
            FOREIGN KEY (user_id) REFERENCES users (id)
        )');

        // Create indexes for performance
        $this->addSql('CREATE INDEX idx_ip_attempt_time ON login_attempts (ip_address, attempted_at)');
        $this->addSql('CREATE INDEX idx_username_attempt_time ON login_attempts (username, attempted_at)');
        $this->addSql('CREATE INDEX idx_user_timestamp ON audit_log (user_id, timestamp)');
        $this->addSql('CREATE INDEX idx_action_timestamp ON audit_log (action, timestamp)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_log');
        $this->addSql('DROP TABLE login_attempts');
        $this->addSql('DROP TABLE users');
    }
}