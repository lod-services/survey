<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class SecureEmailValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof SecureEmail) {
            throw new UnexpectedTypeException($constraint, SecureEmail::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        // Check for null bytes - critical security issue
        if (str_contains($value, "\0")) {
            $this->context->buildViolation($constraint->nullByteMessage)
                ->addViolation();
            return;
        }

        // Check for suspicious patterns that could indicate injection attempts
        $suspiciousPatterns = [
            '/[;<>]/',                          // Command injection characters
            '/\bscript\b/i',                    // XSS script tags
            '/\bdrop\s+table\b/i',              // SQL drop commands
            '/\bunion\s+select\b/i',            // SQL union injection
            '/\binsert\s+into\b/i',             // SQL insert commands
            '/\bdelete\s+from\b/i',             // SQL delete commands
            '/\bupdate\s+.*\bset\b/i',          // SQL update commands
            '/(\*|%|\?){3,}/',                  // Excessive wildcards
            '/\.{3,}/',                         // Multiple dots (path traversal)
            '/\${.*}/',                         // Variable interpolation
            '/%[0-9a-f]{2}/i',                  // URL encoded characters
            '/\\\\[nrtx]/',                     // Escape sequences
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                $this->context->buildViolation($constraint->suspiciousMessage)
                    ->addViolation();
                return;
            }
        }

        // Additional checks for email-specific security issues
        if (substr_count($value, '@') !== 1) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // Check for excessive length (beyond RFC limits + safety margin)
        if (strlen($value) > 300) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }
    }
}