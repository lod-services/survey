<?php

namespace App\Service;

use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Psr\Log\LoggerInterface;

/**
 * Comprehensive input validation and sanitization service
 * Implements circuit breaker pattern for reliability and includes DoS prevention
 */
class ValidationService
{
    // Input length limits for DoS prevention
    private const MAX_TEXT_LENGTH = 1000;
    private const MAX_EMAIL_LENGTH = 254;
    private const MAX_URL_LENGTH = 2048;
    private const MAX_TEXTAREA_LENGTH = 10000;
    private const MAX_FILE_SIZE = 10485760; // 10MB
    
    // Cache configuration
    private const CACHE_TTL = 3600; // 1 hour
    private const CACHE_PREFIX = 'validation_rules_';
    
    public function __construct(
        private ValidatorInterface $validator,
        private ValidationCircuitBreaker $circuitBreaker,
        private AdapterInterface $cache,
        private LoggerInterface $logger,
        private RequestStack $requestStack
    ) {
    }
    
    /**
     * Validate and sanitize input data with circuit breaker protection
     * @param mixed $data Data to validate
     * @param array $constraints Validation constraints
     * @param array $options Validation options
     * @return array{valid: bool, data: mixed, errors: array, sanitized: mixed}
     */
    public function validateAndSanitize($data, array $constraints = [], array $options = []): array
    {
        $startTime = microtime(true);
        
        try {
            // Check for DoS attack patterns
            if ($this->isLikelyDoSAttempt($data)) {
                $this->logSecurityViolation('dos_attempt', $data);
                return $this->createErrorResponse('Input size exceeds security limits', $data);
            }
            
            // Perform validation with circuit breaker protection
            $result = $this->circuitBreaker->execute(
                fn() => $this->performValidation($data, $constraints, $options),
                fn() => $this->fallbackValidation($data, $options)
            );
            
            // Log performance metrics
            $duration = (microtime(true) - $startTime) * 1000;
            if ($duration > 50) { // Log if over 50ms threshold
                $this->logger->warning('Validation performance threshold exceeded', [
                    'duration_ms' => round($duration, 2),
                    'data_size' => $this->getDataSize($data)
                ]);
            }
            
            return $result;
            
        } catch (\Throwable $e) {
            $this->logger->error('Validation service error', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->fallbackValidation($data, $options);
        }
    }
    
    /**
     * Sanitize common data types
     * @param mixed $data Data to sanitize
     * @param string $type Data type (text, email, url, textarea)
     * @return mixed Sanitized data
     */
    public function sanitize($data, string $type = 'text')
    {
        if ($data === null || $data === '') {
            return $data;
        }
        
        return match($type) {
            'text' => $this->sanitizeText($data),
            'email' => $this->sanitizeEmail($data),
            'url' => $this->sanitizeUrl($data),
            'textarea' => $this->sanitizeTextarea($data),
            'numeric' => $this->sanitizeNumeric($data),
            default => $this->sanitizeText($data)
        };
    }
    
    /**
     * Validate specific field types with built-in constraints
     * @param mixed $value Value to validate
     * @param string $type Field type
     * @param array $options Additional validation options
     * @return array Validation result
     */
    public function validateField($value, string $type, array $options = []): array
    {
        $constraints = $this->getConstraintsForType($type, $options);
        return $this->validateAndSanitize($value, $constraints, $options);
    }
    
    /**
     * Get circuit breaker status for monitoring
     */
    public function getCircuitBreakerStatus(): array
    {
        return [
            'state' => $this->circuitBreaker->getState(),
            'failure_count' => $this->circuitBreaker->getFailureCount(),
            'is_healthy' => $this->circuitBreaker->isClosed()
        ];
    }
    
    private function performValidation($data, array $constraints, array $options): array
    {
        // Build constraint collection
        $constraintCollection = new Assert\Collection([
            'fields' => $constraints,
            'allowExtraFields' => $options['allow_extra_fields'] ?? true,
            'allowMissingFields' => $options['allow_missing_fields'] ?? false
        ]);
        
        // Perform validation
        $violations = $this->validator->validate($data, $constraintCollection);
        
        // Process results
        if (count($violations) === 0) {
            $sanitizedData = $this->sanitizeData($data, $constraints);
            return $this->createSuccessResponse($sanitizedData);
        }
        
        $errors = $this->formatViolations($violations);
        $this->logValidationFailure($data, $errors);
        
        return $this->createErrorResponse($errors, $data);
    }
    
    private function fallbackValidation($data, array $options): array
    {
        $this->logger->info('Using fallback validation due to circuit breaker');
        
        // Enhanced security checks even in fallback mode
        $errors = [];
        
        // Critical security validation that must always run
        if ($this->isLikelyDoSAttempt($data)) {
            $this->logSecurityViolation('dos_attempt_fallback', $data);
            return $this->createErrorResponse('Input exceeds basic size limits', $data);
        }
        
        // Check for obvious malicious patterns
        $securityErrors = $this->performCriticalSecurityChecks($data);
        if (!empty($securityErrors)) {
            $this->logSecurityViolation('critical_security_fallback', $data);
            return $this->createErrorResponse($securityErrors, $data);
        }
        
        // Sanitization with enhanced safety
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                $fieldType = $this->guessFieldType($key);
                $sanitizedValue = $this->sanitize($value, $fieldType);
                
                // Double-check sanitized value for safety
                if ($this->isStillDangerous($sanitizedValue)) {
                    $errors[] = "Field '$key' contains potentially dangerous content even after sanitization";
                    continue;
                }
                
                $sanitized[$key] = $sanitizedValue;
            }
        } else {
            $sanitized = $this->sanitize($data);
            if ($this->isStillDangerous($sanitized)) {
                return $this->createErrorResponse('Data contains potentially dangerous content', $data);
            }
        }
        
        if (!empty($errors)) {
            return $this->createErrorResponse($errors, $data);
        }
        
        return $this->createSuccessResponse($sanitized);
    }
    
    private function performCriticalSecurityChecks($data): array
    {
        $errors = [];
        
        if (is_array($data)) {
            array_walk_recursive($data, function($value) use (&$errors) {
                if (is_string($value)) {
                    $securityCheck = $this->checkForCriticalThreats($value);
                    if ($securityCheck) {
                        $errors[] = $securityCheck;
                    }
                }
            });
        } else if (is_string($data)) {
            $securityCheck = $this->checkForCriticalThreats($data);
            if ($securityCheck) {
                $errors[] = $securityCheck;
            }
        }
        
        return $errors;
    }
    
    private function checkForCriticalThreats(string $value): ?string
    {
        // Critical XSS patterns that must be blocked even in fallback
        $criticalXssPatterns = [
            '/<script[^>]*>/i',
            '/javascript:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i',
            '/onclick\s*=/i',
            '/<iframe[^>]*>/i',
            '/<object[^>]*>/i',
            '/<embed[^>]*>/i'
        ];
        
        foreach ($criticalXssPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return 'Critical XSS pattern detected';
            }
        }
        
        // Critical SQL injection patterns
        $criticalSqlPatterns = [
            '/(\bunion\s+select)/i',
            '/(\bdrop\s+table)/i',
            '/(\bdelete\s+from)/i',
            '/(\binsert\s+into)/i',
            '/(\bupdate\s+.+\bset)/i',
            '/(\b(exec|execute)\s*\()/i'
        ];
        
        foreach ($criticalSqlPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return 'Critical SQL injection pattern detected';
            }
        }
        
        return null;
    }
    
    private function isStillDangerous(string $value): bool
    {
        // Check if value is still dangerous after sanitization
        $dangerousPatterns = [
            '/<script/i',
            '/javascript:/i',
            '/onload=/i',
            '/onerror=/i',
            '/union.*select/i',
            '/drop.*table/i'
        ];
        
        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function isLikelyDoSAttempt($data): bool
    {
        if (is_string($data)) {
            return strlen($data) > self::MAX_TEXTAREA_LENGTH;
        }
        
        if (is_array($data)) {
            $totalSize = 0;
            array_walk_recursive($data, function($value) use (&$totalSize) {
                if (is_string($value)) {
                    $totalSize += strlen($value);
                }
            });
            return $totalSize > self::MAX_TEXTAREA_LENGTH * 10; // Allow more for arrays
        }
        
        return false;
    }
    
    private function sanitizeText(string $data): string
    {
        // Truncate to prevent DoS
        $data = substr($data, 0, self::MAX_TEXT_LENGTH);
        
        // XSS prevention
        return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    private function sanitizeEmail(string $data): string
    {
        $data = substr($data, 0, self::MAX_EMAIL_LENGTH);
        return filter_var($data, FILTER_SANITIZE_EMAIL) ?: '';
    }
    
    private function sanitizeUrl(string $data): string
    {
        $data = substr($data, 0, self::MAX_URL_LENGTH);
        return filter_var($data, FILTER_SANITIZE_URL) ?: '';
    }
    
    private function sanitizeTextarea(string $data): string
    {
        $data = substr($data, 0, self::MAX_TEXTAREA_LENGTH);
        return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    private function sanitizeNumeric($data): float|int|null
    {
        if (is_numeric($data)) {
            return is_float($data + 0) ? (float)$data : (int)$data;
        }
        return null;
    }
    
    private function sanitizeData($data, array $constraints): array
    {
        if (!is_array($data)) {
            return ['value' => $this->sanitize($data)];
        }
        
        $sanitized = [];
        foreach ($data as $field => $value) {
            $type = $this->getFieldTypeFromConstraints($field, $constraints);
            $sanitized[$field] = $this->sanitize($value, $type);
        }
        
        return $sanitized;
    }
    
    private function getConstraintsForType(string $type, array $options): array
    {
        $cacheKey = self::CACHE_PREFIX . $type . '_' . md5(serialize($options));
        
        $constraints = $this->cache->get($cacheKey, function() use ($type, $options) {
            return match($type) {
                'email' => [
                    new Assert\NotBlank(),
                    new Assert\Email(),
                    new Assert\Length(['max' => self::MAX_EMAIL_LENGTH])
                ],
                'url' => [
                    new Assert\Url(),
                    new Assert\Length(['max' => self::MAX_URL_LENGTH])
                ],
                'text' => [
                    new Assert\Type('string'),
                    new Assert\Length(['max' => self::MAX_TEXT_LENGTH])
                ],
                'textarea' => [
                    new Assert\Type('string'),
                    new Assert\Length(['max' => self::MAX_TEXTAREA_LENGTH])
                ],
                'numeric' => [
                    new Assert\Type('numeric')
                ],
                default => [new Assert\Type('string')]
            };
        });
        
        return ['value' => $constraints];
    }
    
    private function getFieldTypeFromConstraints(string $field, array $constraints): string
    {
        // Simple field type guessing from field name
        return $this->guessFieldType($field);
    }
    
    private function guessFieldType(string $fieldName): string
    {
        $fieldName = strtolower($fieldName);
        
        if (str_contains($fieldName, 'email')) return 'email';
        if (str_contains($fieldName, 'url') || str_contains($fieldName, 'link')) return 'url';
        if (str_contains($fieldName, 'message') || str_contains($fieldName, 'description') || str_contains($fieldName, 'text')) return 'textarea';
        if (str_contains($fieldName, 'number') || str_contains($fieldName, 'age') || str_contains($fieldName, 'count')) return 'numeric';
        
        return 'text';
    }
    
    private function formatViolations(ConstraintViolationListInterface $violations): array
    {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[] = [
                'field' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
                'invalid_value' => $this->sanitizeForLog($violation->getInvalidValue())
            ];
        }
        return $errors;
    }
    
    private function createSuccessResponse($data): array
    {
        return [
            'valid' => true,
            'data' => $data,
            'errors' => [],
            'sanitized' => $data
        ];
    }
    
    private function createErrorResponse($errors, $originalData): array
    {
        return [
            'valid' => false,
            'data' => $originalData,
            'errors' => is_array($errors) ? $errors : [$errors],
            'sanitized' => $this->sanitize($originalData)
        ];
    }
    
    private function logSecurityViolation(string $type, $data): void
    {
        $request = $this->requestStack->getCurrentRequest();
        
        $this->logger->warning('Security validation violation detected', [
            'violation_type' => $type,
            'data_size' => $this->getDataSize($data),
            'client_ip' => $request?->getClientIp() ?? 'unknown',
            'user_agent' => $request?->headers->get('User-Agent') ?? 'unknown'
        ]);
    }
    
    private function logValidationFailure($data, array $errors): void
    {
        $this->logger->info('Validation failed', [
            'error_count' => count($errors),
            'data_size' => $this->getDataSize($data),
            'errors' => array_slice($errors, 0, 5) // Limit logged errors
        ]);
    }
    
    private function getDataSize($data): int
    {
        return strlen(serialize($data));
    }
    
    private function sanitizeForLog($value): string
    {
        if (is_string($value)) {
            return substr($value, 0, 100) . (strlen($value) > 100 ? '...' : '');
        }
        return gettype($value);
    }
}