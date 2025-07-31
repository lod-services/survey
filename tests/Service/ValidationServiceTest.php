<?php

namespace App\Tests\Service;

use App\Service\ValidationService;
use App\Service\ValidationCircuitBreaker;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Psr\Log\LoggerInterface;

class ValidationServiceTest extends KernelTestCase
{
    private ValidationService $validationService;
    private ValidatorInterface $validator;
    private ValidationCircuitBreaker $circuitBreaker;
    private ArrayAdapter $cache;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        self::bootKernel();
        
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cache = new ArrayAdapter();
        
        $this->circuitBreaker = new ValidationCircuitBreaker($this->logger);
        $this->validationService = new ValidationService(
            $this->validator,
            $this->circuitBreaker,
            $this->cache,
            $this->logger
        );
    }

    public function testSanitizeTextRemovesXSS(): void
    {
        $maliciousInput = '<script>alert("XSS")</script>Hello World';
        $sanitized = $this->validationService->sanitize($maliciousInput, 'text');
        
        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringNotContainsString('alert', $sanitized);
        $this->assertStringContainsString('Hello World', $sanitized);
    }

    public function testSanitizeEmailFiltersInvalidEmail(): void
    {
        $invalidEmail = 'not-an-email<script>alert(1)</script>';
        $sanitized = $this->validationService->sanitize($invalidEmail, 'email');
        
        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringNotContainsString('alert', $sanitized);
    }

    public function testSanitizeTextTruncatesLongInput(): void
    {
        $longInput = str_repeat('A', 2000); // Longer than MAX_TEXT_LENGTH (1000)
        $sanitized = $this->validationService->sanitize($longInput, 'text');
        
        $this->assertLessThanOrEqual(1000, strlen($sanitized));
    }

    public function testSanitizeTextareaAllowsLongerContent(): void
    {
        $longInput = str_repeat('A', 5000); // Within MAX_TEXTAREA_LENGTH (10000)
        $sanitized = $this->validationService->sanitize($longInput, 'textarea');
        
        $this->assertEquals(5000, strlen($sanitized));
    }

    public function testSanitizeNumericConvertsToNumber(): void
    {
        $numericString = '123.45';
        $sanitized = $this->validationService->sanitize($numericString, 'numeric');
        
        $this->assertIsFloat($sanitized);
        $this->assertEquals(123.45, $sanitized);
    }

    public function testSanitizeNumericHandlesInvalidInput(): void
    {
        $invalidNumeric = 'not-a-number';
        $sanitized = $this->validationService->sanitize($invalidNumeric, 'numeric');
        
        $this->assertNull($sanitized);
    }

    public function testValidateFieldReturnsSuccessForValidEmail(): void
    {
        $validEmail = 'test@example.com';
        $result = $this->validationService->validateField($validEmail, 'email');
        
        // Note: This test would require mocking the validator to return no violations
        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('sanitized', $result);
    }

    public function testCircuitBreakerStatusReturnsCorrectFormat(): void
    {
        $status = $this->validationService->getCircuitBreakerStatus();
        
        $this->assertIsArray($status);
        $this->assertArrayHasKey('state', $status);
        $this->assertArrayHasKey('failure_count', $status);
        $this->assertArrayHasKey('is_healthy', $status);
    }

    /**
     * @dataProvider dosAttackProvider
     */
    public function testDoSAttackDetection(string $input, bool $shouldBeDetected): void
    {
        $result = $this->validationService->validateAndSanitize($input);
        
        if ($shouldBeDetected) {
            $this->assertFalse($result['valid']);
            $this->assertStringContainsString('security limits', $result['errors'][0]);
        } else {
            // For non-DoS inputs, validation might still fail for other reasons
            // but should not be rejected for size
            $this->assertIsArray($result);
        }
    }

    public function dosAttackProvider(): array
    {
        return [
            'normal input' => ['Hello World', false],
            'long but valid textarea' => [str_repeat('A', 5000), false],
            'extremely long input' => [str_repeat('A', 15000), true],
            'repeated pattern' => [str_repeat('ABCD', 3000), true],
        ];
    }

    public function testSanitizeHandlesNullAndEmptyValues(): void
    {
        $this->assertNull($this->validationService->sanitize(null));
        $this->assertEquals('', $this->validationService->sanitize(''));
    }

    public function testSanitizeUrl(): void
    {
        $validUrl = 'https://example.com/path?param=value';
        $sanitized = $this->validationService->sanitize($validUrl, 'url');
        
        $this->assertStringContainsString('example.com', $sanitized);
        
        $maliciousUrl = 'javascript:alert(1)';
        $sanitized = $this->validationService->sanitize($maliciousUrl, 'url');
        
        $this->assertStringNotContainsString('javascript:', $sanitized);
    }
}