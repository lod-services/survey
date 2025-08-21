<?php

namespace App;

use App\Exception\SecurityConfigurationException;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function __construct(string $environment, bool $debug)
    {
        parent::__construct($environment, $debug);
        $this->validateEnvironment();
    }

    private function validateEnvironment(): void
    {
        // Only validate in production or when explicitly required
        if ($this->getEnvironment() !== 'prod' && !($_ENV['FORCE_SECURITY_VALIDATION'] ?? false)) {
            return;
        }

        $appSecret = $_ENV['APP_SECRET'] ?? '';
        
        if (empty($appSecret)) {
            throw new SecurityConfigurationException(
                'APP_SECRET environment variable is required but not set. ' .
                'Please configure a secure secret (32+ characters) in your environment.'
            );
        }
        
        if (mb_strlen($appSecret) < 32) {
            throw new SecurityConfigurationException(
                'APP_SECRET must be at least 32 characters for security. ' .
                'Current length: ' . mb_strlen($appSecret) . ' characters. ' .
                'Please generate a stronger secret.'
            );
        }
    }
}
