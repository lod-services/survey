<?php

namespace App\EventListener;

use App\Security\AppSecretValidator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * APP_SECRET Validation Event Listener
 * 
 * Validates APP_SECRET cryptographic strength during application startup.
 * Implements early validation to prevent application startup with weak secrets.
 * 
 * Validation occurs once per application lifecycle to avoid performance impact.
 */
class AppSecretValidationListener
{
    private ParameterBagInterface $parameterBag;
    private LoggerInterface $logger;
    private bool $validationComplete = false;
    private bool $strictMode;
    
    public function __construct(
        ParameterBagInterface $parameterBag,
        LoggerInterface $logger,
        bool $strictMode = true
    ) {
        $this->parameterBag = $parameterBag;
        $this->logger = $logger;
        $this->strictMode = $strictMode;
    }
    
    /**
     * Validate APP_SECRET on kernel events
     * 
     * Uses KernelEvents::KERNEL for one-time validation during application bootstrap.
     * This approach ensures validation happens early but only once per application lifecycle.
     */
    public function onKernelEvent(KernelEvent $event): void
    {
        // Skip if validation already completed (avoid multiple validations)
        if ($this->validationComplete) {
            return;
        }
        
        // Only validate on master request to avoid duplicate validation
        if (!$event->isMainRequest()) {
            return;
        }
        
        $this->validateAppSecret();
        $this->validationComplete = true;
    }
    
    private function validateAppSecret(): void
    {
        try {
            $appSecret = $this->parameterBag->get('kernel.secret');
            
            if (empty($appSecret)) {
                $this->handleValidationFailure(
                    'APP_SECRET is not configured or empty',
                    'critical',
                    [
                        'Generate a secure secret with: openssl rand -base64 32',
                        'Add it to your .env.local file: APP_SECRET=<generated_secret>',
                        'Verify configuration with: php csrf_verification.php'
                    ]
                );
                return;
            }
            
            $validation = AppSecretValidator::validate($appSecret);
            
            if (!$validation['valid']) {
                $this->handleValidationFailure(
                    'APP_SECRET does not meet security requirements',
                    'critical',
                    $validation['recommendations'],
                    $validation['errors']
                );
                return;
            }
            
            // Log warnings for improvement opportunities
            if (!empty($validation['warnings'])) {
                $this->logger->warning('APP_SECRET validation warnings detected', [
                    'warnings' => $validation['warnings'],
                    'recommendations' => $validation['recommendations'],
                    'metrics' => $validation['metrics']
                ]);
            }
            
            // Log successful validation in debug mode
            $this->logger->debug('APP_SECRET validation successful', [
                'length' => $validation['metrics']['length'],
                'format' => $validation['metrics']['format'],
                'entropy_bits' => $validation['metrics']['total_entropy_bits'],
                'shannon_entropy' => $validation['metrics']['shannon_entropy']
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('APP_SECRET validation failed with exception', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            if ($this->strictMode) {
                throw new ServiceUnavailableHttpException(
                    300, // Retry after 5 minutes
                    'Application security validation failed. Please check configuration and try again.'
                );
            }
        }
    }
    
    private function handleValidationFailure(
        string $message,
        string $logLevel,
        array $recommendations = [],
        array $errors = []
    ): void {
        $context = [
            'validation_failed' => true,
            'recommendations' => $recommendations,
            'errors' => $errors
        ];
        
        // Log the security issue
        $this->logger->log($logLevel, $message, $context);
        
        // In strict mode, prevent application startup
        if ($this->strictMode) {
            $errorMessage = $this->buildUserErrorMessage($message, $recommendations, $errors);
            throw new ServiceUnavailableHttpException(
                300, // Retry after 5 minutes
                $errorMessage
            );
        }
        
        // In non-strict mode, log warning but allow application to continue
        $this->logger->warning('APP_SECRET validation failed but continuing in non-strict mode', $context);
    }
    
    private function buildUserErrorMessage(string $message, array $recommendations, array $errors): string
    {
        $errorMessage = "🔐 Security Configuration Error\n\n";
        $errorMessage .= "Issue: {$message}\n\n";
        
        if (!empty($errors)) {
            $errorMessage .= "Specific Problems:\n";
            foreach ($errors as $error) {
                $errorMessage .= "• {$error}\n";
            }
            $errorMessage .= "\n";
        }
        
        if (!empty($recommendations)) {
            $errorMessage .= "Quick Fix:\n";
            foreach ($recommendations as $recommendation) {
                $errorMessage .= "• {$recommendation}\n";
            }
            $errorMessage .= "\n";
        }
        
        $errorMessage .= "For detailed analysis, run: php bin/security/app_secret_analysis.php\n";
        $errorMessage .= "For quick validation, run: php csrf_verification.php";
        
        return $errorMessage;
    }
    
    /**
     * Enable or disable strict mode
     * 
     * Strict mode (default): Application fails to start with invalid APP_SECRET
     * Non-strict mode: Application logs warnings but continues
     */
    public function setStrictMode(bool $strict): void
    {
        $this->strictMode = $strict;
    }
    
    /**
     * Check if validation has been completed
     */
    public function isValidationComplete(): bool
    {
        return $this->validationComplete;
    }
    
    /**
     * Reset validation state (useful for testing)
     */
    public function resetValidation(): void
    {
        $this->validationComplete = false;
    }
}