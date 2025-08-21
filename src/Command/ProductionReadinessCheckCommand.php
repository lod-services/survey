<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:production-readiness-check',
    description: 'Validates production environment configuration and security requirements'
)]
class ProductionReadinessCheckCommand extends Command
{
    public function __construct(
        private ParameterBagInterface $parameterBag
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Production Readiness Check');
        
        $allChecksPass = true;
        
        // Check APP_SECRET
        $appSecret = $this->parameterBag->get('app.secret');
        
        if (empty($appSecret)) {
            $io->error('APP_SECRET environment variable is not set');
            $io->note('Please configure a secure secret (32+ characters) in your environment.');
            $allChecksPass = false;
        } elseif (mb_strlen($appSecret) < 32) {
            $io->error('APP_SECRET is too short: ' . mb_strlen($appSecret) . ' characters (minimum: 32)');
            $io->note('Please generate a stronger secret with at least 32 characters.');
            $allChecksPass = false;
        } elseif (!$this->validateSecretEntropy($appSecret)) {
            $io->error('APP_SECRET has insufficient entropy (not enough character variety)');
            $io->note('Please generate a more random secret with diverse characters.');
            $allChecksPass = false;
        } else {
            $io->success('APP_SECRET is properly configured (' . mb_strlen($appSecret) . ' characters, good entropy)');
        }
        
        // Check APP_ENV
        $appEnv = $this->parameterBag->get('kernel.environment');
        if ($appEnv === 'prod') {
            $io->success('APP_ENV is set to production');
        } else {
            $io->warning('APP_ENV is set to: ' . $appEnv);
            $io->note('For production deployment, APP_ENV should be set to "prod"');
        }
        
        // Overall status
        if ($allChecksPass) {
            $io->success('All production readiness checks passed');
            return Command::SUCCESS;
        } else {
            $io->error('Some production readiness checks failed');
            $io->note('Please fix the issues above before deploying to production.');
            return Command::FAILURE;
        }
    }

    private function validateSecretEntropy(string $secret): bool
    {
        // Check for sufficient character variety (at least 16 unique characters)
        return count(array_unique(str_split($secret))) >= 16;
    }
}
