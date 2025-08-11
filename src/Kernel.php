<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        parent::boot();
        $this->validateAppSecret();
    }

    private function validateAppSecret(): void
    {
        // Skip validation in production for performance unless explicitly enabled
        $env = $_ENV['APP_ENV'] ?? 'prod';
        $forceValidation = $_ENV['APP_SECRET_VALIDATION'] ?? 'auto';
        
        if ($env === 'prod' && $forceValidation !== 'enabled') {
            return;
        }
        
        $appSecret = $_ENV['APP_SECRET'] ?? '';
        
        if (empty($appSecret)) {
            throw new \RuntimeException(
                'APP_SECRET is not configured. This is a critical security vulnerability. ' .
                'Please set a secure 64-character secret in your environment variables or .env.local file. ' .
                'Generate one with: openssl rand -hex 32'
            );
        }
        
        if (strlen($appSecret) < 32) {
            throw new \RuntimeException(
                sprintf(
                    'APP_SECRET is too short (%d characters). For security, it must be at least 32 characters long. ' .
                    'Recommended: 64 characters. Generate one with: openssl rand -hex 32',
                    strlen($appSecret)
                )
            );
        }
        
        if ($appSecret === 'your_generated_secret_here_64_chars_minimum') {
            throw new \RuntimeException(
                'APP_SECRET is set to the template placeholder value. ' .
                'This is a security vulnerability. Please set a real secret. ' .
                'Generate one with: openssl rand -hex 32'
            );
        }
    }
}