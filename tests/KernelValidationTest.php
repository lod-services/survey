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
        // Store original environment values
        $this->originalEnv = [
            'APP_SECRET' => $_ENV['APP_SECRET'] ?? null,
            'FORCE_SECURITY_VALIDATION' => $_ENV['FORCE_SECURITY_VALIDATION'] ?? null,
        ];
    }
    
    protected function tearDown(): void
    {
        // Restore original environment values
        foreach ($this->originalEnv as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
    }
    
    public function testKernelConstructorSucceedsWithValidAppSecretInProd(): void
    {
        $_ENV['APP_SECRET'] = 'this_is_a_valid_32_character_secret_value';
        
        // This should not throw an exception
        $this->expectNotToPerformAssertions();
        new Kernel('prod', false);
    }
    
    public function testKernelConstructorSucceedsInDevWithoutValidation(): void
    {
        $_ENV['APP_SECRET'] = 'short';
        
        // This should not throw an exception in dev environment
        $this->expectNotToPerformAssertions();
        new Kernel('dev', false);
    }
    
    public function testKernelConstructorFailsWithEmptyAppSecretInProd(): void
    {
        $_ENV['APP_SECRET'] = '';
        
        $this->expectException(SecurityConfigurationException::class);
        $this->expectExceptionMessage('APP_SECRET environment variable is required but not set');
        
        new Kernel('prod', false);
    }
    
    public function testKernelConstructorFailsWithShortAppSecretInProd(): void
    {
        $_ENV['APP_SECRET'] = 'this_is_only_31_characters_long';
        
        $this->expectException(SecurityConfigurationException::class);
        $this->expectExceptionMessage('APP_SECRET must be at least 32 characters for security');
        
        new Kernel('prod', false);
    }
    
    public function testKernelConstructorFailsWithForceValidationInDev(): void
    {
        $_ENV['APP_SECRET'] = 'short';
        $_ENV['FORCE_SECURITY_VALIDATION'] = 'true';
        
        $this->expectException(SecurityConfigurationException::class);
        $this->expectExceptionMessage('APP_SECRET must be at least 32 characters for security');
        
        new Kernel('dev', false);
    }
}
