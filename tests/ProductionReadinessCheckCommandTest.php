<?php

namespace App\Tests;

use App\Command\ProductionReadinessCheckCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class ProductionReadinessCheckCommandTest extends TestCase
{
    public function testCommandSucceedsWithValidConfiguration(): void
    {
        $parameterBag = new ParameterBag([
            'app.secret' => 'this_is_a_valid_32_character_secret_value',
            'kernel.environment' => 'prod'
        ]);
        
        $command = new ProductionReadinessCheckCommand($parameterBag);
        $commandTester = new CommandTester($command);
        
        $exitCode = $commandTester->execute([]);
        
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('All production readiness checks passed', $commandTester->getDisplay());
        $this->assertStringContainsString('APP_SECRET is properly configured', $commandTester->getDisplay());
    }
    
    public function testCommandFailsWithEmptyAppSecret(): void
    {
        $parameterBag = new ParameterBag([
            'app.secret' => '',
            'kernel.environment' => 'prod'
        ]);
        
        $command = new ProductionReadinessCheckCommand($parameterBag);
        $commandTester = new CommandTester($command);
        
        $exitCode = $commandTester->execute([]);
        
        $this->assertEquals(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('APP_SECRET environment variable is not set', $commandTester->getDisplay());
        $this->assertStringContainsString('Some production readiness checks failed', $commandTester->getDisplay());
    }
    
    public function testCommandFailsWithShortAppSecret(): void
    {
        $parameterBag = new ParameterBag([
            'app.secret' => 'short',
            'kernel.environment' => 'prod'
        ]);
        
        $command = new ProductionReadinessCheckCommand($parameterBag);
        $commandTester = new CommandTester($command);
        
        $exitCode = $commandTester->execute([]);
        
        $this->assertEquals(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('APP_SECRET is too short: 5 characters', $commandTester->getDisplay());
        $this->assertStringContainsString('Some production readiness checks failed', $commandTester->getDisplay());
    }
    
    public function testCommandWarnsAboutNonProdEnvironment(): void
    {
        $parameterBag = new ParameterBag([
            'app.secret' => 'this_is_a_valid_32_character_secret_value',
            'kernel.environment' => 'dev'
        ]);
        
        $command = new ProductionReadinessCheckCommand($parameterBag);
        $commandTester = new CommandTester($command);
        
        $exitCode = $commandTester->execute([]);
        
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('APP_ENV is set to: dev', $commandTester->getDisplay());
        $this->assertStringContainsString('For production deployment, APP_ENV should be set to "prod"', $commandTester->getDisplay());
    }
}
