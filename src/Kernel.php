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

        // Warn if using a weak or predictable secret pattern
        if (preg_match('/^[a-f0-9]+$/', $appSecret) && strlen($appSecret) < 64) {
            error_log(
                'WARNING: APP_SECRET appears to be shorter than recommended (64 characters). ' .
                'Consider generating a longer secret with: openssl rand -hex 32'
            );
        }
    }
}