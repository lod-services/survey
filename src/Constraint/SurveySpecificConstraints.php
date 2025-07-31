<?php

namespace App\Constraint;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Survey-specific validation constraints
 * Provides specialized validation rules for survey application data
 */

/**
 * Validates that survey response text doesn't contain malicious patterns
 */
class SafeSurveyText extends Constraint
{
    public string $message = 'Survey response contains potentially unsafe content.';
    public bool $allowHtml = false;
    public int $maxLength = 10000;
}

class SafeSurveyTextValidator extends ConstraintValidator
{
    // Patterns that might indicate malicious input
    private const SUSPICIOUS_PATTERNS = [
        // Script injection patterns
        '/<\s*script[^>]*>/i',
        '/<\s*\/\s*script\s*>/i',
        '/javascript\s*:/i',
        '/vbscript\s*:/i',
        '/on\w+\s*=/i', // Event handlers like onclick, onload
        
        // SQL injection patterns
        '/(\bUNION\b|\bSELECT\b|\bINSERT\b|\bUPDATE\b|\bDELETE\b|\bDROP\b).*(\bFROM\b|\bWHERE\b|\bINTO\b)/i',
        '/\b(OR|AND)\s+\d+\s*=\s*\d+/i',
        '/\'\s*(OR|AND)\s+\'/i',
        
        // Command injection patterns
        '/[;&|`$(){}[\]]/i',
        
        // Path traversal patterns
        '/\.\.\/|\.\.\\\/i',
        
        // PHP code injection
        '/<\?php|<\?=/i',
    ];
    
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof SafeSurveyText) {
            throw new \InvalidArgumentException('Expected SafeSurveyText constraint');
        }
        
        if ($value === null || $value === '') {
            return;
        }
        
        if (!is_string($value)) {
            $this->context->buildViolation('Survey text must be a string')
                ->addViolation();
            return;
        }
        
        // Check length
        if (strlen($value) > $constraint->maxLength) {
            $this->context->buildViolation('Survey response is too long (maximum {{ limit }} characters)')
                ->setParameter('{{ limit }}', $constraint->maxLength)
                ->addViolation();
            return;
        }
        
        // Check for suspicious patterns
        foreach (self::SUSPICIOUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $value)) {
                $this->context->buildViolation($constraint->message)
                    ->addViolation();
                return;
            }
        }
        
        // If HTML is not allowed, check for HTML tags
        if (!$constraint->allowHtml && $this->containsHtml($value)) {
            $this->context->buildViolation('HTML tags are not allowed in survey responses')
                ->addViolation();
        }
    }
    
    private function containsHtml(string $value): bool
    {
        return $value !== strip_tags($value);
    }
}

/**
 * Validates survey question types and formats
 */
class ValidSurveyQuestion extends Constraint
{
    public string $message = 'Invalid survey question format.';
}

class ValidSurveyQuestionValidator extends ConstraintValidator
{
    private const VALID_QUESTION_TYPES = [
        'text',
        'textarea', 
        'email',
        'number',
        'select',
        'radio',
        'checkbox',
        'date',
        'rating'
    ];
    
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidSurveyQuestion) {
            throw new \InvalidArgumentException('Expected ValidSurveyQuestion constraint');
        }
        
        if (!is_array($value)) {
            $this->context->buildViolation('Survey question must be an array')
                ->addViolation();
            return;
        }
        
        // Validate required fields
        $requiredFields = ['type', 'text', 'required'];
        foreach ($requiredFields as $field) {
            if (!isset($value[$field])) {
                $this->context->buildViolation("Survey question missing required field: {{ field }}")
                    ->setParameter('{{ field }}', $field)
                    ->addViolation();
                return;
            }
        }
        
        // Validate question type
        if (!in_array($value['type'], self::VALID_QUESTION_TYPES)) {
            $this->context->buildViolation('Invalid question type: {{ type }}')
                ->setParameter('{{ type }}', $value['type'])
                ->addViolation();
        }
        
        // Validate question text
        if (empty(trim($value['text']))) {
            $this->context->buildViolation('Question text cannot be empty')
                ->addViolation();
        }
        
        // Validate options for select/radio/checkbox questions
        if (in_array($value['type'], ['select', 'radio', 'checkbox']) && empty($value['options'])) {
            $this->context->buildViolation('Question type {{ type }} requires options')
                ->setParameter('{{ type }}', $value['type'])
                ->addViolation();
        }
    }
}

/**
 * Validates file uploads for surveys (e.g., document attachments)
 */
class SafeFileUpload extends Constraint
{
    public string $message = 'File upload failed security validation.';
    public array $allowedMimeTypes = [
        'image/jpeg',
        'image/png', 
        'image/gif',
        'application/pdf',
        'text/plain',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    public int $maxSize = 10485760; // 10MB
}

class SafeFileUploadValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof SafeFileUpload) {
            throw new \InvalidArgumentException('Expected SafeFileUpload constraint');
        }
        
        if ($value === null) {
            return;
        }
        
        // Check if it's a valid uploaded file
        if (!is_array($value) || !isset($value['tmp_name'], $value['size'], $value['type'])) {
            $this->context->buildViolation('Invalid file upload format')
                ->addViolation();
            return;
        }
        
        // Check file size
        if ($value['size'] > $constraint->maxSize) {
            $this->context->buildViolation('File is too large (maximum {{ limit }} bytes)')
                ->setParameter('{{ limit }}', $constraint->maxSize)
                ->addViolation();
        }
        
        // Check MIME type
        if (!in_array($value['type'], $constraint->allowedMimeTypes)) {
            $this->context->buildViolation('File type {{ type }} is not allowed')
                ->setParameter('{{ type }}', $value['type'])
                ->addViolation();
        }
        
        // Additional security checks would go here
        // (virus scanning, content validation, etc.)
    }
}

/**
 * Validates email addresses with additional survey-specific rules
 */
class ValidSurveyEmail extends Constraint
{
    public string $message = 'Invalid email address for survey submission.';
    public bool $allowDisposable = false;
    public array $blockedDomains = [];
}

class ValidSurveyEmailValidator extends ConstraintValidator
{
    // Common disposable email domains to block
    private const DISPOSABLE_DOMAINS = [
        '10minutemail.com',
        'guerrillamail.com',
        'mailinator.com',
        'yopmail.com',
        'tempmail.org'
    ];
    
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidSurveyEmail) {
            throw new \InvalidArgumentException('Expected ValidSurveyEmail constraint');
        }
        
        if ($value === null || $value === '') {
            return;
        }
        
        // Basic email validation
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }
        
        $domain = substr(strrchr($value, '@'), 1);
        
        // Check blocked domains
        if (in_array($domain, $constraint->blockedDomains)) {
            $this->context->buildViolation('Email domain {{ domain }} is not allowed')
                ->setParameter('{{ domain }}', $domain)
                ->addViolation();
        }
        
        // Check disposable email domains if not allowed
        if (!$constraint->allowDisposable && in_array($domain, self::DISPOSABLE_DOMAINS)) {
            $this->context->buildViolation('Disposable email addresses are not allowed')
                ->addViolation();
        }
    }
}