<?php

namespace App\Constraint;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Input length constraints for DoS prevention
 * Implements the length limits specified in the security requirements
 */

/**
 * Composite constraint that validates input length and prevents DoS attacks
 */
class InputLengthLimits extends Constraint
{
    public string $message = 'Input exceeds maximum allowed length for security purposes.';
    public string $dosMessage = 'Input rejected due to potential DoS attack pattern.';
    public int $textLimit = 1000;
    public int $emailLimit = 254;
    public int $urlLimit = 2048;  
    public int $textareaLimit = 10000;
    public int $fileLimit = 10485760; // 10MB in bytes
    public bool $strict = true; // If true, reject on limit breach; if false, truncate
}

class InputLengthLimitsValidator extends ConstraintValidator
{
    // Patterns that might indicate automated/DoS attacks
    private const DOS_PATTERNS = [
        // Repeated characters (more than 1000 consecutive same chars)
        '/(.)\1{1000,}/',
        
        // Extremely long words (single word > 200 chars)
        '/\S{200,}/',
        
        // Excessive whitespace
        '/\s{1000,}/',
        
        // Base64-like patterns (potential data exfiltration)
        '/[A-Za-z0-9+\/]{5000,}={0,2}/',
        
        // Repeated patterns that might indicate automated input
        '/(.{10,})\1{10,}/',
    ];
    
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof InputLengthLimits) {
            throw new \InvalidArgumentException('Expected InputLengthLimits constraint');
        }
        
        if ($value === null || $value === '') {
            return;
        }
        
        // Handle array inputs (recursive validation)
        if (is_array($value)) {
            $this->validateArrayInput($value, $constraint);
            return;
        }
        
        // Handle file uploads
        if (is_array($value) && isset($value['size'], $value['tmp_name'])) {
            $this->validateFileInput($value, $constraint);
            return;
        }
        
        // Convert to string for validation
        $stringValue = (string) $value;
        $length = strlen($stringValue);
        
        // Check for DoS attack patterns first
        if ($this->isDoSPattern($stringValue)) {
            $this->context->buildViolation($constraint->dosMessage)
                ->addViolation();
            return;
        }
        
        // Determine field type and appropriate limit
        $fieldType = $this->guessFieldType($this->context->getPropertyPath());
        $limit = $this->getLimitForFieldType($fieldType, $constraint);
        
        if ($length > $limit) {
            if ($constraint->strict) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ limit }}', $limit)
                    ->setParameter('{{ actual }}', $length)
                    ->setParameter('{{ type }}', $fieldType)
                    ->addViolation();
            }
            // If not strict, we would truncate, but that's handled in the service layer
        }
    }
    
    private function validateArrayInput(array $value, InputLengthLimits $constraint): void
    {
        $totalSize = 0;
        $itemCount = 0;
        
        // Calculate total data size and check individual items
        array_walk_recursive($value, function($item) use (&$totalSize, &$itemCount, $constraint) {
            $itemCount++;
            if (is_string($item)) {
                $totalSize += strlen($item);
                
                // Check individual item for DoS patterns
                if ($this->isDoSPattern($item)) {
                    $this->context->buildViolation($constraint->dosMessage)
                        ->addViolation();
                }
            }
        });
        
        // Check for array-based DoS attacks
        if ($itemCount > 10000) { // More than 10k array items
            $this->context->buildViolation('Array contains too many items ({{ count }}), potential DoS attack')
                ->setParameter('{{ count }}', $itemCount)
                ->addViolation();
        }
        
        if ($totalSize > $constraint->textareaLimit * 10) { // 100MB total for arrays
            $this->context->buildViolation('Array data size too large ({{ size }} bytes), potential DoS attack')
                ->setParameter('{{ size }}', $totalSize)
                ->addViolation();
        }
    }
    
    private function validateFileInput(array $fileData, InputLengthLimits $constraint): void
    {
        if (isset($fileData['size']) && $fileData['size'] > $constraint->fileLimit) {
            $this->context->buildViolation('File size exceeds limit ({{ limit }} bytes)')
                ->setParameter('{{ limit }}', $constraint->fileLimit)
                ->setParameter('{{ actual }}', $fileData['size'])
                ->addViolation();
        }
    }
    
    private function isDoSPattern(string $value): bool
    {
        foreach (self::DOS_PATTERNS as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function guessFieldType(string $propertyPath): string
    {
        $path = strtolower($propertyPath);
        
        if (str_contains($path, 'email')) return 'email';
        if (str_contains($path, 'url') || str_contains($path, 'link') || str_contains($path, 'website')) return 'url';
        if (str_contains($path, 'message') || str_contains($path, 'description') || 
            str_contains($path, 'comment') || str_contains($path, 'feedback') ||
            str_contains($path, 'textarea')) return 'textarea';
        
        return 'text';
    }
    
    private function getLimitForFieldType(string $fieldType, InputLengthLimits $constraint): int
    {
        return match($fieldType) {
            'email' => $constraint->emailLimit,
            'url' => $constraint->urlLimit,
            'textarea' => $constraint->textareaLimit,
            default => $constraint->textLimit
        };
    }
}

/**
 * Rate limiting constraint to prevent rapid-fire submissions
 */
class RateLimit extends Constraint
{
    public string $message = 'Too many requests. Please wait before submitting again.';
    public int $maxRequests = 10;
    public int $timeWindow = 60; // seconds
    public string $identifier = 'ip'; // 'ip', 'session', or custom key
}

class RateLimitValidator extends ConstraintValidator
{
    // WARNING: In-memory rate limiting with periodic cleanup to prevent memory leaks
    // For production use, replace with Redis, database, or external rate limiting service
    // This implementation includes automatic cleanup but still not recommended for high-traffic production
    private static array $requestCounts = [];
    
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof RateLimit) {
            throw new \InvalidArgumentException('Expected RateLimit constraint');
        }
        
        $identifier = $this->getIdentifier($constraint->identifier);
        $currentTime = time();
        $windowStart = $currentTime - $constraint->timeWindow;
        
        // Clean old entries periodically to prevent memory leaks
        $this->cleanupOldEntries($windowStart);
        
        // Clean current identifier's old entries
        if (isset(self::$requestCounts[$identifier])) {
            self::$requestCounts[$identifier] = array_filter(
                self::$requestCounts[$identifier],
                fn($timestamp) => $timestamp > $windowStart
            );
        }
        
        // Count requests in current window
        $requestCount = count(self::$requestCounts[$identifier] ?? []);
        
        if ($requestCount >= $constraint->maxRequests) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ limit }}', $constraint->maxRequests)
                ->setParameter('{{ window }}', $constraint->timeWindow)
                ->addViolation();
            return;
        }
        
        // Record this request
        self::$requestCounts[$identifier][] = $currentTime;
    }
    
    private function getIdentifier(string $type): string
    {
        return match($type) {
            'ip' => $this->getClientIp(),
            'session' => session_id() ?: 'no-session',
            default => $type
        };
    }
    
    private function getClientIp(): string
    {
        // Priority order for IP detection behind proxies/load balancers
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    private function cleanupOldEntries(int $windowStart): void
    {
        // Periodically clean up old entries to prevent memory leaks
        static $lastCleanup = 0;
        $now = time();
        
        // Clean up every 5 minutes
        if ($now - $lastCleanup > 300) {
            foreach (self::$requestCounts as $identifier => $timestamps) {
                self::$requestCounts[$identifier] = array_filter(
                    $timestamps,
                    fn($timestamp) => $timestamp > $windowStart
                );
                
                // Remove empty arrays
                if (empty(self::$requestCounts[$identifier])) {
                    unset(self::$requestCounts[$identifier]);
                }
            }
            $lastCleanup = $now;
        }
    }
}

/**
 * Character set validation to prevent encoding attacks
 */
class SafeCharacterSet extends Constraint
{
    public string $message = 'Input contains unsafe characters.';
    public array $allowedCharsets = ['UTF-8'];
    public bool $allowControlChars = false;
    public array $blockedChars = []; // Specific characters to block
}

class SafeCharacterSetValidator extends ConstraintValidator
{
    // Potentially dangerous control characters
    private const DANGEROUS_CONTROL_CHARS = [
        "\x00", // NULL
        "\x08", // Backspace
        "\x0B", // Vertical Tab
        "\x0C", // Form Feed
        "\x0E", // Shift Out
        "\x0F", // Shift In
        "\x1A", // Substitute
        "\x1B", // Escape
    ];
    
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof SafeCharacterSet) {
            throw new \InvalidArgumentException('Expected SafeCharacterSet constraint');
        }
        
        if ($value === null || $value === '') {
            return;
        }
        
        $stringValue = (string) $value;
        
        // Check encoding
        foreach ($constraint->allowedCharsets as $charset) {
            if (!mb_check_encoding($stringValue, $charset)) {
                $this->context->buildViolation('Input encoding is not valid {{ charset }}')
                    ->setParameter('{{ charset }}', $charset)
                    ->addViolation();
                return;
            }
        }
        
        // Check for dangerous control characters
        if (!$constraint->allowControlChars) {
            foreach (self::DANGEROUS_CONTROL_CHARS as $char) {
                if (str_contains($stringValue, $char)) {
                    $this->context->buildViolation($constraint->message)
                        ->addViolation();
                    return;
                }
            }
        }
        
        // Check for specifically blocked characters
        foreach ($constraint->blockedChars as $blockedChar) {
            if (str_contains($stringValue, $blockedChar)) {
                $this->context->buildViolation('Input contains blocked character: {{ char }}')
                    ->setParameter('{{ char }}', bin2hex($blockedChar))
                    ->addViolation();
                return;
            }
        }
    }
}