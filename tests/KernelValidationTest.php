<?php

namespace App\Tests;

use App\Exception\SecurityConfigurationException;
use App\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class KernelValidationTest extends TestCase
{
    public function testKernelBootSucceedsWithValidAppSecret(): void
    {
        $kernel = new Kernel('test', false);
        
        // Create a mock container with valid APP_SECRET
        $container = new ContainerBuilder();
        $container->setParameterBag(new ParameterBag([
            'app.secret' => 'this_is_a_valid_32_character_secret_value'
        ]));
        
        // Use reflection to set the container
        $reflection = new \ReflectionClass($kernel);
        $containerProperty = $reflection->getProperty('container');
        $containerProperty->setAccessible(true);
        $containerProperty->setValue($kernel, $container);
        
        // This should not throw an exception
        $this->expectNotToPerformAssertions();
        
        // Use reflection to call validateEnvironment directly
        $method = $reflection->getMethod('validateEnvironment');
        $method->setAccessible(true);
        $method->invoke($kernel);
    }
    
    public function testKernelBootFailsWithEmptyAppSecret(): void
    {
        $kernel = new Kernel('test', false);
        
        // Create a mock container with empty APP_SECRET
        $container = new ContainerBuilder();
        $container->setParameterBag(new ParameterBag([
            'app.secret' => ''
        ]));
        
        // Use reflection to set the container
        $reflection = new \ReflectionClass($kernel);
        $containerProperty = $reflection->getProperty('container');
        $containerProperty->setAccessible(true);
        $containerProperty->setValue($kernel, $container);
        
        $this->expectException(SecurityConfigurationException::class);
        $this->expectExceptionMessage('APP_SECRET environment variable is required but not set');
        
        // Use reflection to call validateEnvironment directly
        $method = $reflection->getMethod('validateEnvironment');
        $method->setAccessible(true);
        $method->invoke($kernel);
    }
    
    public function testKernelBootFailsWithShortAppSecret(): void
    {
        $kernel = new Kernel('test', false);
        
        // Create a mock container with short APP_SECRET (31 characters)
        $container = new ContainerBuilder();
        $container->setParameterBag(new ParameterBag([
            'app.secret' => 'this_is_only_31_characters_long'
        ]));
        
        // Use reflection to set the container
        $reflection = new \ReflectionClass($kernel);
        $containerProperty = $reflection->getProperty('container');
        $containerProperty->setAccessible(true);
        $containerProperty->setValue($kernel, $container);
        
        $this->expectException(SecurityConfigurationException::class);
        $this->expectExceptionMessage('APP_SECRET must be at least 32 characters for security');
        
        // Use reflection to call validateEnvironment directly
        $method = $reflection->getMethod('validateEnvironment');
        $method->setAccessible(true);
        $method->invoke($kernel);
    }
}