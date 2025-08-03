<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function __construct(string $environment, bool $debug)
    {
        $this->validateEnvironment();
        parent::__construct($environment, $debug);
    }

    private function validateEnvironment(): void
    {
        $appSecret = $_ENV['APP_SECRET'] ?? $_SERVER['APP_SECRET'] ?? '';
        
        if (empty($appSecret)) {
            throw new \RuntimeException(
                'APP_SECRET environment variable is not set. ' .
                'Please configure a secure APP_SECRET in .env.local or as an environment variable. ' .
                'Generate one with: openssl rand -hex 32'
            );
        }

        if (strlen($appSecret) < 32) {
            throw new \RuntimeException(
                sprintf(
                    'APP_SECRET must be at least 32 characters long, got %d characters. ' .
                    'Generate a secure secret with: openssl rand -hex 32',
                    strlen($appSecret)
                )
            );
        }

        // Enhanced validation for weak or predictable secret patterns
        $this->validateSecretStrength($appSecret);
    }

    private function validateSecretStrength(string $appSecret): void
    {
        // Check for common weak patterns
        $weakPatterns = [
            '/^(.)\1+$/' => 'contains only repeated characters',
            '/^(..)\1+$/' => 'contains only repeated pairs',
            '/^123+|abc+|111+|000+/i' => 'contains sequential or repeated simple patterns',
            '/^(password|secret|key|test|demo|example)/i' => 'starts with common weak words',
            '/^[a-f0-9]{1,31}$/' => 'appears to be a short hex string (less than 32 chars)',
        ];

        foreach ($weakPatterns as $pattern => $description) {
            if (preg_match($pattern, $appSecret)) {
                error_log(
                    "WARNING: APP_SECRET {$description}. " .
                    'This may be a security risk. Generate a secure secret with: openssl rand -hex 32'
                );
                break;
            }
        }

        // Warn if using a hex-only secret shorter than recommended
        if (preg_match('/^[a-f0-9]+$/', $appSecret) && strlen($appSecret) < 64) {
            error_log(
                'WARNING: APP_SECRET appears to be shorter than recommended (64 characters). ' .
                'Consider generating a longer secret with: openssl rand -hex 32'
            );
        }

        // Check for insufficient character diversity
        $uniqueChars = count(array_unique(str_split($appSecret)));
        $totalChars = strlen($appSecret);
        if ($uniqueChars < max(8, $totalChars * 0.25)) {
            error_log(
                'WARNING: APP_SECRET has low character diversity. ' .
                'Consider generating a more random secret with: openssl rand -hex 32'
            );
        }
    }
}