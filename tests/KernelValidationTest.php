<?php

namespace App\Tests;

use App\Exception\SecurityConfigurationException;
use App\Kernel;
use PHPUnit\Framework\TestCase;

class KernelValidationTest extends TestCase
{
    private array $originalEnv = [];

    protected function setUp(): void
    {
        // Backup original environment variables
        $this->originalEnv = [
            'APP_SECRET' => $_ENV['APP_SECRET'] ?? null,
            'FORCE_SECURITY_VALIDATION' => $_ENV['FORCE_SECURITY_VALIDATION'] ?? null
        ];
    }

    protected function tearDown(): void
    {
        // Restore original environment variables
        foreach ($this->originalEnv as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
    }

    public function testKernelBootSucceedsWithValidAppSecret(): void
    {
        $_ENV['APP_SECRET'] = 'this_is_a_valid_32_character_secret_value';
        $_ENV['FORCE_SECURITY_VALIDATION'] = 'true';
        
        // This should not throw an exception
        $this->expectNotToPerformAssertions();
        new Kernel('test', false);
    }
    
    public function testKernelBootFailsWithEmptyAppSecret(): void
    {
        $_ENV['APP_SECRET'] = '';
        $_ENV['FORCE_SECURITY_VALIDATION'] = 'true';
        
        $this->expectException(SecurityConfigurationException::class);
        $this->expectExceptionMessage('APP_SECRET environment variable is required but not set');
        
        new Kernel('test', false);
    }
    
    public function testKernelBootFailsWithShortAppSecret(): void
    {
        $_ENV['APP_SECRET'] = 'this_is_only_31_characters_long';
        $_ENV['FORCE_SECURITY_VALIDATION'] = 'true';
        
        $this->expectException(SecurityConfigurationException::class);
        $this->expectExceptionMessage('APP_SECRET must be at least 32 characters for security');
        
        new Kernel('test', false);
    }

    public function testKernelBootSkipsValidationInDevelopment(): void
    {
        $_ENV['APP_SECRET'] = 'short';
        unset($_ENV['FORCE_SECURITY_VALIDATION']);
        
        // This should not throw an exception in development mode
        $this->expectNotToPerformAssertions();
        new Kernel('dev', false);
    }
}
