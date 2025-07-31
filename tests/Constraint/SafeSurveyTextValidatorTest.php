<?php

namespace App\Tests\Constraint;

use App\Constraint\SafeSurveyText;
use App\Constraint\SafeSurveyTextValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class SafeSurveyTextValidatorTest extends TestCase
{
    private SafeSurveyTextValidator $validator;
    private ExecutionContextInterface $context;
    private ConstraintViolationBuilderInterface $violationBuilder;

    protected function setUp(): void
    {
        $this->validator = new SafeSurveyTextValidator();
        $this->context = $this->createMock(ExecutionContextInterface::class);
        $this->violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        
        $this->validator->initialize($this->context);
    }

    public function testValidTextPassesValidation(): void
    {
        $constraint = new SafeSurveyText();
        
        $this->context->expects($this->never())
                     ->method('buildViolation');

        $this->validator->validate('This is a safe text input', $constraint);
    }

    public function testNullAndEmptyValuesAreSkipped(): void
    {
        $constraint = new SafeSurveyText();
        
        $this->context->expects($this->never())
                     ->method('buildViolation');

        $this->validator->validate(null, $constraint);
        $this->validator->validate('', $constraint);
    }

    public function testNonStringValueCausesViolation(): void
    {
        $constraint = new SafeSurveyText();
        
        $this->context->expects($this->once())
                     ->method('buildViolation')
                     ->with('Survey text must be a string')
                     ->willReturn($this->violationBuilder);
                     
        $this->violationBuilder->expects($this->once())
                              ->method('addViolation');

        $this->validator->validate(123, $constraint);
    }

    public function testExcessiveLengthCausesViolation(): void
    {
        $constraint = new SafeSurveyText(['maxLength' => 100]);
        $longText = str_repeat('A', 200);
        
        $this->context->expects($this->once())
                     ->method('buildViolation')
                     ->with('Survey response is too long (maximum {{ limit }} characters)')
                     ->willReturn($this->violationBuilder);
                     
        $this->violationBuilder->expects($this->once())
                              ->method('setParameter')
                              ->with('{{ limit }}', 100)
                              ->willReturn($this->violationBuilder);
                              
        $this->violationBuilder->expects($this->once())
                              ->method('addViolation');

        $this->validator->validate($longText, $constraint);
    }

    /**
     * @dataProvider maliciousInputProvider
     */
    public function testMaliciousInputCausesViolation(string $maliciousInput): void
    {
        $constraint = new SafeSurveyText();
        
        $this->context->expects($this->once())
                     ->method('buildViolation')
                     ->with($constraint->message)
                     ->willReturn($this->violationBuilder);
                     
        $this->violationBuilder->expects($this->once())
                              ->method('addViolation');

        $this->validator->validate($maliciousInput, $constraint);
    }

    public function maliciousInputProvider(): array
    {
        return [
            'script tag' => ['<script>alert("XSS")</script>'],
            'javascript url' => ['javascript:alert(1)'],
            'vbscript url' => ['vbscript:alert(1)'],
            'onclick event' => ['<div onclick="alert(1)">Click me</div>'],
            'sql injection basic' => ["'; DROP TABLE users; --"],
            'sql injection union' => ['UNION SELECT * FROM users WHERE 1=1'],
            'command injection' => ['test; rm -rf /'],
            'path traversal' => ['../../etc/passwd'],
            'php code' => ['<?php echo "hack"; ?>'],
        ];
    }

    public function testHtmlNotAllowedByDefault(): void
    {
        $constraint = new SafeSurveyText(['allowHtml' => false]);
        $htmlInput = '<p>This is HTML content</p>';
        
        $this->context->expects($this->once())
                     ->method('buildViolation')
                     ->with('HTML tags are not allowed in survey responses')
                     ->willReturn($this->violationBuilder);
                     
        $this->violationBuilder->expects($this->once())
                              ->method('addViolation');

        $this->validator->validate($htmlInput, $constraint);
    }

    public function testHtmlAllowedWhenConfigured(): void
    {
        $constraint = new SafeSurveyText(['allowHtml' => true]);
        $htmlInput = '<p>This is allowed HTML content</p>';
        
        $this->context->expects($this->never())
                     ->method('buildViolation');

        $this->validator->validate($htmlInput, $constraint);
    }

    public function testSafeHtmlStillCheckedForMaliciousPatterns(): void
    {
        $constraint = new SafeSurveyText(['allowHtml' => true]);
        $maliciousHtml = '<p>Content</p><script>alert("XSS")</script>';
        
        $this->context->expects($this->once())
                     ->method('buildViolation')
                     ->with($constraint->message)
                     ->willReturn($this->violationBuilder);
                     
        $this->violationBuilder->expects($this->once())
                              ->method('addViolation');

        $this->validator->validate($maliciousHtml, $constraint);
    }

    public function testCaseInsensitivePatternMatching(): void
    {
        $constraint = new SafeSurveyText();
        $uppercaseScript = '<SCRIPT>alert("XSS")</SCRIPT>';
        
        $this->context->expects($this->once())
                     ->method('buildViolation')
                     ->willReturn($this->violationBuilder);
                     
        $this->violationBuilder->expects($this->once())
                              ->method('addViolation');

        $this->validator->validate($uppercaseScript, $constraint);
    }

    public function testWhitespaceInPatternsDetected(): void
    {
        $constraint = new SafeSurveyText();
        $scriptWithSpaces = '<  script  >alert("XSS")</  script  >';
        
        $this->context->expects($this->once())
                     ->method('buildViolation')
                     ->willReturn($this->violationBuilder);
                     
        $this->violationBuilder->expects($this->once())
                              ->method('addViolation');

        $this->validator->validate($scriptWithSpaces, $constraint);
    }
}