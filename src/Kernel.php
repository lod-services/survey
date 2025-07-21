<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        $this->validateAppSecret();
        parent::boot();
    }

    private function validateAppSecret(): void
    {
        $appSecret = $_ENV['APP_SECRET'] ?? '';
        
        if (empty($appSecret)) {
            throw new \RuntimeException(
                'APP_SECRET environment variable is empty or not set. ' .
                'This is a critical security vulnerability. ' .
                'Please generate a secure secret using "openssl rand -hex 32" ' .
                'and set it in your .env file.'
            );
        }

        if (strlen($appSecret) < 32) {
            throw new \RuntimeException(
                'APP_SECRET is too short. For security reasons, it must be at least 32 characters long. ' .
                'Please generate a secure secret using "openssl rand -hex 32".'
            );
        }
    }
}