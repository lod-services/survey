#!/usr/bin/env php
<?php

/**
 * APP_SECRET Security Analysis Script
 * 
 * Comprehensive security analysis tool for APP_SECRET validation across all environments.
 * This script provides detailed cryptographic analysis and recommendations for improving
 * application security through proper secret management.
 * 
 * Usage:
 *   php bin/security/app_secret_analysis.php [options]
 * 
 * Options:
 *   --env=<env>     Analyze specific environment file (.env.<env>)
 *   --generate      Generate new secure APP_SECRET suggestions
 *   --format=<fmt>  Output format: text (default), json, summary
 *   --help          Show this help message
 * 
 * Examples:
 *   php bin/security/app_secret_analysis.php
 *   php bin/security/app_secret_analysis.php --env=dev
 *   php bin/security/app_secret_analysis.php --generate
 *   php bin/security/app_secret_analysis.php --format=json
 */

// Include the Symfony autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Security\AppSecretValidator;

class AppSecretAnalyzer
{
    private string $projectRoot;
    private array $options;
    
    public function __construct(string $projectRoot, array $options = [])
    {
        $this->projectRoot = $projectRoot;
        $this->options = $options;
    }
    
    public function run(): void
    {
        if (isset($this->options['help'])) {
            $this->showHelp();
            return;
        }
        
        if (isset($this->options['generate'])) {
            $this->generateSecrets();
            return;
        }
        
        echo $this->getHeader();
        
        $envFiles = $this->discoverEnvironmentFiles();
        $results = [];
        
        foreach ($envFiles as $envFile) {
            $envName = $this->getEnvironmentName($envFile);
            
            // Skip if specific environment requested and this isn't it
            if (isset($this->options['env']) && $this->options['env'] !== $envName) {
                continue;
            }
            
            $secret = $this->extractAppSecret($envFile);
            $results[$envName] = [
                'file' => $envFile,
                'secret_found' => !empty($secret),
                'validation' => $secret ? AppSecretValidator::validate($secret) : null
            ];
        }
        
        $this->outputResults($results);
        $this->showRecommendations($results);
    }
    
    private function getHeader(): string
    {
        $format = $this->options['format'] ?? 'text';
        
        if ($format === 'json') {
            return '';
        }
        
        return "🔐 APP_SECRET Security Analysis\n" .
               "===============================\n" .
               "Timestamp: " . date('Y-m-d H:i:s T') . "\n" .
               "Project: " . basename($this->projectRoot) . "\n\n";
    }
    
    private function discoverEnvironmentFiles(): array
    {
        $envFiles = [];
        $patterns = [
            '.env',
            '.env.local',
            '.env.dev',
            '.env.test',
            '.env.prod',
            '.env.staging'
        ];
        
        foreach ($patterns as $pattern) {
            $filePath = $this->projectRoot . '/' . $pattern;
            if (file_exists($filePath)) {
                $envFiles[] = $filePath;
            }
        }
        
        return $envFiles;
    }
    
    private function getEnvironmentName(string $filePath): string
    {
        $fileName = basename($filePath);
        
        if ($fileName === '.env') {
            return 'default';
        }
        
        if ($fileName === '.env.local') {
            return 'local';
        }
        
        // Extract environment from .env.{env} pattern
        if (preg_match('/\.env\.(.+)$/', $fileName, $matches)) {
            return $matches[1];
        }
        
        return 'unknown';
    }
    
    private function extractAppSecret(string $envFile): ?string
    {
        $content = @file_get_contents($envFile);
        if ($content === false) {
            return null;
        }
        
        // Look for APP_SECRET=value pattern
        if (preg_match('/^APP_SECRET=(.*)$/m', $content, $matches)) {
            $secret = trim($matches[1]);
            // Remove quotes if present
            $secret = trim($secret, '"\'');
            return empty($secret) ? null : $secret;
        }
        
        return null;
    }
    
    private function outputResults(array $results): void
    {
        $format = $this->options['format'] ?? 'text';
        
        switch ($format) {
            case 'json':
                echo json_encode($results, JSON_PRETTY_PRINT) . "\n";
                break;
                
            case 'summary':
                $this->outputSummary($results);
                break;
                
            case 'text':
            default:
                $this->outputDetailed($results);
                break;
        }
    }
    
    private function outputDetailed(array $results): void
    {
        foreach ($results as $envName => $result) {
            echo "📁 Environment: {$envName}\n";
            echo "📄 File: " . basename($result['file']) . "\n";
            
            if (!$result['secret_found']) {
                echo "❌ APP_SECRET not found or empty\n";
                echo "💡 Add APP_SECRET to this environment file\n\n";
                continue;
            }
            
            $validation = $result['validation'];
            $metrics = $validation['metrics'];
            
            echo "📊 Metrics:\n";
            echo "   Length: {$metrics['length']} characters\n";
            echo "   Format: " . ucfirst($metrics['format']) . "\n";
            echo "   Shannon Entropy: {$metrics['shannon_entropy']} bits/char\n";
            echo "   Total Entropy: {$metrics['total_entropy_bits']} bits\n";
            echo "   Status: " . ($validation['valid'] ? '✅ VALID' : '❌ INVALID') . "\n";
            
            if (!empty($validation['errors'])) {
                echo "\n🚫 Errors:\n";
                foreach ($validation['errors'] as $error) {
                    echo "   ❌ {$error}\n";
                }
            }
            
            if (!empty($validation['warnings'])) {
                echo "\n⚠️  Warnings:\n";
                foreach ($validation['warnings'] as $warning) {
                    echo "   ⚠️  {$warning}\n";
                }
            }
            
            if (!empty($validation['recommendations'])) {
                echo "\n💡 Recommendations:\n";
                foreach ($validation['recommendations'] as $recommendation) {
                    echo "   💡 {$recommendation}\n";
                }
            }
            
            echo "\n" . str_repeat('-', 60) . "\n\n";
        }
    }
    
    private function outputSummary(array $results): void
    {
        echo "Summary Report:\n";
        echo "===============\n";
        
        $totalFiles = count($results);
        $validSecrets = 0;
        $invalidSecrets = 0;
        $missingSecrets = 0;
        
        foreach ($results as $envName => $result) {
            if (!$result['secret_found']) {
                $missingSecrets++;
                echo "❌ {$envName}: Missing APP_SECRET\n";
            } elseif ($result['validation']['valid']) {
                $validSecrets++;
                echo "✅ {$envName}: Valid APP_SECRET\n";
            } else {
                $invalidSecrets++;
                echo "❌ {$envName}: Invalid APP_SECRET\n";
            }
        }
        
        echo "\nStatistics:\n";
        echo "  Total files: {$totalFiles}\n";
        echo "  Valid secrets: {$validSecrets}\n";
        echo "  Invalid secrets: {$invalidSecrets}\n";
        echo "  Missing secrets: {$missingSecrets}\n";
        
        if ($invalidSecrets > 0 || $missingSecrets > 0) {
            echo "\n🚨 Security Action Required!\n";
            echo "  Run with --generate option to get secure replacement secrets\n";
        } else {
            echo "\n🎉 All APP_SECRET configurations are secure!\n";
        }
    }
    
    private function showRecommendations(array $results): void
    {
        $format = $this->options['format'] ?? 'text';
        
        if ($format === 'json') {
            return;
        }
        
        $hasIssues = false;
        foreach ($results as $result) {
            if (!$result['secret_found'] || !$result['validation']['valid']) {
                $hasIssues = true;
                break;
            }
        }
        
        if (!$hasIssues) {
            echo "🎉 All APP_SECRET configurations meet security requirements!\n\n";
            return;
        }
        
        echo "🔧 Security Improvement Recommendations:\n";
        echo "========================================\n\n";
        
        echo "1. Generate secure secrets:\n";
        echo "   php bin/security/app_secret_analysis.php --generate\n\n";
        
        echo "2. For manual generation:\n";
        echo "   Base64 (recommended): openssl rand -base64 32\n";
        echo "   Hexadecimal: openssl rand -hex 32\n\n";
        
        echo "3. Update environment files with new secrets\n\n";
        
        echo "4. Verify configuration:\n";
        echo "   php csrf_verification.php\n";
        echo "   php bin/security/app_secret_analysis.php\n\n";
        
        echo "5. For production deployment:\n";
        echo "   - Use environment variables instead of .env files\n";
        echo "   - Rotate secrets regularly\n";
        echo "   - Store secrets in secure vaults (AWS Secrets Manager, etc.)\n\n";
    }
    
    private function generateSecrets(): void
    {
        echo "🔐 Secure APP_SECRET Generation\n";
        echo "===============================\n\n";
        
        echo "🎲 Generated Secure Secrets:\n\n";
        
        echo "Base64 Format (Recommended):\n";
        for ($i = 1; $i <= 3; $i++) {
            $secret = AppSecretValidator::generateSecureSecret('base64');
            $validation = AppSecretValidator::validate($secret);
            echo "  Option {$i}: {$secret}\n";
            echo "             Entropy: {$validation['metrics']['total_entropy_bits']} bits\n";
        }
        
        echo "\nHexadecimal Format:\n";
        for ($i = 1; $i <= 3; $i++) {
            $secret = AppSecretValidator::generateSecureSecret('hex');
            $validation = AppSecretValidator::validate($secret);
            echo "  Option {$i}: {$secret}\n";
            echo "             Entropy: {$validation['metrics']['total_entropy_bits']} bits\n";
        }
        
        echo "\n📋 Usage Instructions:\n";
        echo "1. Choose one of the generated secrets above\n";
        echo "2. Update your environment file(s):\n";
        echo "   APP_SECRET=<chosen_secret>\n";
        echo "3. Verify with: php bin/security/app_secret_analysis.php\n\n";
        
        echo "⚠️  Security Reminders:\n";
        echo "- Each environment should have a unique secret\n";
        echo "- Never commit secrets to version control\n";
        echo "- Use .env.local for local development\n";
        echo "- Use environment variables for production\n";
    }
    
    private function showHelp(): void
    {
        $scriptName = basename(__FILE__);
        echo "APP_SECRET Security Analysis Tool\n";
        echo "=================================\n\n";
        echo "Usage: php bin/security/{$scriptName} [options]\n\n";
        echo "Options:\n";
        echo "  --env=<env>     Analyze specific environment (dev, prod, test, etc.)\n";
        echo "  --generate      Generate new secure APP_SECRET options\n";
        echo "  --format=<fmt>  Output format: text (default), json, summary\n";
        echo "  --help          Show this help message\n\n";
        echo "Examples:\n";
        echo "  php bin/security/{$scriptName}                 # Analyze all environments\n";
        echo "  php bin/security/{$scriptName} --env=dev       # Analyze only .env.dev\n";
        echo "  php bin/security/{$scriptName} --generate      # Generate secure secrets\n";
        echo "  php bin/security/{$scriptName} --format=json   # JSON output\n";
        echo "  php bin/security/{$scriptName} --format=summary # Summary only\n\n";
    }
}

// Parse command line arguments
function parseArgs(array $argv): array
{
    $options = [];
    
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        
        if ($arg === '--help') {
            $options['help'] = true;
        } elseif ($arg === '--generate') {
            $options['generate'] = true;
        } elseif (strpos($arg, '--env=') === 0) {
            $options['env'] = substr($arg, 6);
        } elseif (strpos($arg, '--format=') === 0) {
            $options['format'] = substr($arg, 9);
        }
    }
    
    return $options;
}

// Main execution
try {
    $projectRoot = dirname(__DIR__, 2); // Go up two levels from bin/security/
    $options = parseArgs($argv);
    
    $analyzer = new AppSecretAnalyzer($projectRoot, $options);
    $analyzer->run();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}