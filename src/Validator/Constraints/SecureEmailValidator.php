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
        // Each pattern targets specific attack vectors for comprehensive security
        $suspiciousPatterns = [
            '/[;<>]/',                          // Command injection: semicolon, less-than, greater-than chars
            '/\bscript\b/i',                    // XSS prevention: detects script tag keywords
            '/\bdrop\s+table\b/i',              // SQL injection: DROP TABLE statements
            '/\bunion\s+select\b/i',            // SQL injection: UNION SELECT for data extraction
            '/\binsert\s+into\b/i',             // SQL injection: INSERT INTO for data manipulation
            '/\bdelete\s+from\b/i',             // SQL injection: DELETE FROM for data destruction
            '/\bupdate\s+.*\bset\b/i',          // SQL injection: UPDATE...SET for data modification
            '/(\*|%|\?){3,}/',                  // Wildcard abuse: 3+ consecutive wildcards indicate fuzzing
            '/\.{3,}/',                         // Path traversal: multiple dots for directory navigation
            '/\${.*}/',                         // Template injection: variable interpolation syntax
            '/%[0-9a-f]{2}/i',                  // URL encoding: percent-encoded chars may hide payloads
            '/\\\\[nrtx]/',                     // Escape sequences: backslash-escaped chars for injection
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

        // Check for excessive length - RFC 5321 limits local part to 64 chars and domain to 253 chars
        // Adding safety margin of ~20 chars gives us 300 bytes total to prevent buffer overflow attacks
        if (strlen($value) > 300) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }
    }
}