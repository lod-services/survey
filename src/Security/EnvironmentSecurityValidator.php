<?php

namespace App\Security;

use App\Security\AppSecretValidator;

/**
 * Environment-Specific Security Validator
 * 
 * Applies different security validation rules based on the application environment.
 * Ensures appropriate security configurations for development, staging, and production.
 */
class EnvironmentSecurityValidator
{
    public const ENV_DEVELOPMENT = 'dev';
    public const ENV_TEST = 'test';
    public const ENV_STAGING = 'staging';
    public const ENV_PRODUCTION = 'prod';
    
    // Environment-specific security requirements
    private const SECURITY_POLICIES = [
        self::ENV_DEVELOPMENT => [
            'min_entropy_bits' => 128,        // Relaxed for dev convenience
            'require_unique_secret' => false, // Allow shared dev secrets
            'allow_weak_patterns' => true,    // More permissive for testing
            'require_https' => false,         // Local dev may use HTTP
            'session_secure' => false,        // Local dev flexibility
            'strict_validation' => false      // Warnings instead of errors
        ],
        self::ENV_TEST => [
            'min_entropy_bits' => 128,        // Test data can be predictable
            'require_unique_secret' => false, // Test secrets can be shared
            'allow_weak_patterns' => true,    // Test secrets may have patterns
            'require_https' => false,         // Test environments
            'session_secure' => false,        // Test flexibility
            'strict_validation' => false      // Don't block tests
        ],
        self::ENV_STAGING => [
            'min_entropy_bits' => 256,        // Production-like security
            'require_unique_secret' => true,  // Unique secrets required
            'allow_weak_patterns' => false,   // No weak patterns
            'require_https' => true,          // HTTPS required
            'session_secure' => true,         // Secure session cookies
            'strict_validation' => true       // Strict enforcement
        ],
        self::ENV_PRODUCTION => [
            'min_entropy_bits' => 256,        // Maximum security
            'require_unique_secret' => true,  // Unique secrets mandatory
            'allow_weak_patterns' => false,   // No weak patterns allowed
            'require_https' => true,          // HTTPS mandatory
            'session_secure' => true,         // Secure cookies mandatory
            'strict_validation' => true       // Strict enforcement
        ]
    ];
    
    // Known insecure secrets that should never be used in any environment
    private const FORBIDDEN_SECRETS = [
        'changeme',
        'password',
        'secret',
        'test',
        'demo',
        'admin',
        'root',
        '123456',
        'abc123',
        'ThisTokenIsNotSoSecretChangeIt', // Symfony default
        '6b1f17348df0767f83847020fb8c3119', // Old weak secret from issue
    ];
    
    private string $environment;
    private array $policy;
    
    public function __construct(string $environment)
    {
        $this->environment = $environment;
        $this->policy = self::SECURITY_POLICIES[$environment] ?? self::SECURITY_POLICIES[self::ENV_PRODUCTION];
    }
    
    /**
     * Validate configuration for the current environment
     */
    public function validateEnvironment(array $config = []): array
    {
        $result = [
            'valid' => true,
            'environment' => $this->environment,
            'policy' => $this->policy,
            'errors' => [],
            'warnings' => [],
            'recommendations' => []
        ];
        
        // Validate APP_SECRET with environment-specific rules
        if (isset($config['app_secret'])) {
            $secretValidation = $this->validateAppSecretForEnvironment($config['app_secret']);
            $result = $this->mergeValidationResults($result, $secretValidation);
        }
        
        // Validate session configuration
        if (isset($config['session'])) {
            $sessionValidation = $this->validateSessionConfiguration($config['session']);
            $result = $this->mergeValidationResults($result, $sessionValidation);
        }
        
        // Validate HTTPS requirements
        if (isset($config['request_context'])) {
            $httpsValidation = $this->validateHttpsRequirements($config['request_context']);
            $result = $this->mergeValidationResults($result, $httpsValidation);
        }
        
        // Environment-specific configuration checks
        $envValidation = $this->validateEnvironmentSpecificConfig($config);
        $result = $this->mergeValidationResults($result, $envValidation);
        
        return $result;
    }
    
    /**
     * Validate APP_SECRET with environment-specific requirements
     */
    private function validateAppSecretForEnvironment(string $appSecret): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'recommendations' => []
        ];
        
        // Check for forbidden secrets
        if (in_array($appSecret, self::FORBIDDEN_SECRETS, true)) {
            $result['errors'][] = "APP_SECRET uses a known insecure value that must never be used";
            $result['recommendations'][] = "Generate a new secure secret immediately: openssl rand -base64 32";
            $result['valid'] = false;
        }
        
        // Use base validation
        $baseValidation = AppSecretValidator::validate($appSecret);
        
        // Apply environment-specific entropy requirements
        $requiredEntropy = $this->policy['min_entropy_bits'];
        $actualEntropy = $baseValidation['metrics']['total_entropy_bits'];
        
        if ($actualEntropy < $requiredEntropy) {
            $message = sprintf(
                'APP_SECRET entropy (%.1f bits) below %s environment requirement (%d bits)',
                $actualEntropy,
                $this->environment,
                $requiredEntropy
            );
            
            if ($this->policy['strict_validation']) {
                $result['errors'][] = $message;
                $result['valid'] = false;
            } else {
                $result['warnings'][] = $message;
            }
        }
        
        // Apply weak pattern policies
        if (!$this->policy['allow_weak_patterns'] && !empty($baseValidation['errors'])) {
            foreach ($baseValidation['errors'] as $error) {
                if (strpos($error, 'pattern') !== false) {
                    $result['errors'][] = "Weak patterns not allowed in {$this->environment} environment: {$error}";
                    $result['valid'] = false;
                }
            }
        }
        
        // Merge base validation results (respecting environment policy)
        if ($this->policy['strict_validation']) {
            $result['errors'] = array_merge($result['errors'], $baseValidation['errors']);
            if (!$baseValidation['valid']) {
                $result['valid'] = false;
            }
        } else {
            // Convert base errors to warnings in non-strict environments
            $result['warnings'] = array_merge($result['warnings'], $baseValidation['errors']);
        }
        
        $result['warnings'] = array_merge($result['warnings'], $baseValidation['warnings']);
        $result['recommendations'] = array_merge($result['recommendations'], $baseValidation['recommendations']);
        
        return $result;
    }
    
    /**
     * Validate session configuration for environment
     */
    private function validateSessionConfiguration(array $sessionConfig): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'recommendations' => []
        ];
        
        // Check secure cookie requirement
        if ($this->policy['session_secure']) {
            $cookieSecure = $sessionConfig['cookie_secure'] ?? 'auto';
            
            if ($cookieSecure !== true && $cookieSecure !== 'auto') {
                $message = "Session cookies should be secure in {$this->environment} environment";
                $result['errors'][] = $message;
                $result['recommendations'][] = "Set framework.session.cookie_secure to true or auto";
                $result['valid'] = false;
            }
        }
        
        // Check SameSite configuration
        $sameSite = $sessionConfig['cookie_samesite'] ?? null;
        if ($this->environment === self::ENV_PRODUCTION && $sameSite !== 'strict') {
            $result['warnings'][] = "Consider setting cookie_samesite to 'strict' for maximum security in production";
            $result['recommendations'][] = "Set framework.session.cookie_samesite to 'strict'";
        }
        
        return $result;
    }
    
    /**
     * Validate HTTPS requirements
     */
    private function validateHttpsRequirements(array $requestContext): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'recommendations' => []
        ];
        
        if ($this->policy['require_https']) {
            $isHttps = $requestContext['scheme'] === 'https';
            
            if (!$isHttps) {
                $message = "HTTPS is required in {$this->environment} environment";
                $result['errors'][] = $message;
                $result['recommendations'][] = "Configure web server to enforce HTTPS";
                $result['recommendations'][] = "Set up SSL/TLS certificates";
                $result['valid'] = false;
            }
        }
        
        return $result;
    }
    
    /**
     * Environment-specific configuration validation
     */
    private function validateEnvironmentSpecificConfig(array $config): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'recommendations' => []
        ];
        
        switch ($this->environment) {
            case self::ENV_PRODUCTION:
                $result = $this->validateProductionConfig($config, $result);
                break;
                
            case self::ENV_STAGING:
                $result = $this->validateStagingConfig($config, $result);
                break;
                
            case self::ENV_DEVELOPMENT:
                $result = $this->validateDevelopmentConfig($config, $result);
                break;
                
            case self::ENV_TEST:
                $result = $this->validateTestConfig($config, $result);
                break;
        }
        
        return $result;
    }
    
    private function validateProductionConfig(array $config, array $result): array
    {
        // Production-specific validations
        if (isset($config['debug']) && $config['debug'] === true) {
            $result['errors'][] = "Debug mode must be disabled in production";
            $result['recommendations'][] = "Set APP_DEBUG=0 in production environment";
            $result['valid'] = false;
        }
        
        if (isset($config['profiler']) && $config['profiler'] === true) {
            $result['warnings'][] = "Web profiler should be disabled in production";
            $result['recommendations'][] = "Disable web profiler in production configuration";
        }
        
        return $result;
    }
    
    private function validateStagingConfig(array $config, array $result): array
    {
        // Staging should mirror production security
        if (isset($config['debug']) && $config['debug'] === true) {
            $result['warnings'][] = "Debug mode should be disabled in staging to mirror production";
            $result['recommendations'][] = "Set APP_DEBUG=0 in staging environment";
        }
        
        return $result;
    }
    
    private function validateDevelopmentConfig(array $config, array $result): array
    {
        // Development-specific recommendations
        if (!isset($config['env_local_template'])) {
            $result['recommendations'][] = "Ensure .env.local.template exists for developer onboarding";
        }
        
        return $result;
    }
    
    private function validateTestConfig(array $config, array $result): array
    {
        // Test environment should be predictable
        if (isset($config['app_secret']) && strlen($config['app_secret']) > 64) {
            $result['warnings'][] = "Test environment can use shorter secrets for faster test execution";
        }
        
        return $result;
    }
    
    /**
     * Merge validation results
     */
    private function mergeValidationResults(array $base, array $additional): array
    {
        $base['errors'] = array_merge($base['errors'], $additional['errors']);
        $base['warnings'] = array_merge($base['warnings'], $additional['warnings']);
        $base['recommendations'] = array_merge($base['recommendations'], $additional['recommendations']);
        
        if (!$additional['valid']) {
            $base['valid'] = false;
        }
        
        return $base;
    }
    
    /**
     * Get security policy for the current environment
     */
    public function getSecurityPolicy(): array
    {
        return $this->policy;
    }
    
    /**
     * Check if environment allows relaxed validation
     */
    public function isStrictValidation(): bool
    {
        return $this->policy['strict_validation'];
    }
    
    /**
     * Get environment name
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }
    
    /**
     * Get all available environments
     */
    public static function getAvailableEnvironments(): array
    {
        return array_keys(self::SECURITY_POLICIES);
    }
    
    /**
     * Create validator for specific environment
     */
    public static function forEnvironment(string $environment): self
    {
        return new self($environment);
    }
}