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
        
        $this->validateSecurityConfiguration();
    }

    private function validateSecurityConfiguration(): void
    {
        try {
            $appSecret = $this->getContainer()->getParameter('kernel.secret');
        } catch (\Exception $e) {
            return;
        }
        
        if (empty($appSecret)) {
            throw new \RuntimeException(
                'SECURITY ERROR: APP_SECRET is not configured. ' .
                'Please set a secure 64-character secret in .env.local or environment variables. ' .
                'Generate one with: php -r "echo bin2hex(random_bytes(32));"'
            );
        }
        
        if (strlen($appSecret) < 64) {
            throw new \RuntimeException(
                sprintf(
                    'SECURITY ERROR: APP_SECRET must be at least 64 characters long for security. ' .
                    'Current length: %d characters. ' .
                    'Generate a secure 64-character secret with: php -r "echo bin2hex(random_bytes(32));"',
                    strlen($appSecret)
                )
            );
        }
    }
}