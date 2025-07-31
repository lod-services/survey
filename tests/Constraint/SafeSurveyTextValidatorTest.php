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
            // XSS Attacks
            'script tag' => ['<script>alert("XSS")</script>'],
            'script tag uppercase' => ['<SCRIPT>ALERT("XSS")</SCRIPT>'],
            'script with spaces' => ['< script >alert("XSS")< /script >'],
            'javascript url' => ['javascript:alert(1)'],
            'vbscript url' => ['vbscript:alert(1)'],
            'onclick event' => ['<div onclick="alert(1)">Click me</div>'],
            'onload event' => ['<img onload="alert(1)" src="x">'],
            'onerror event' => ['<img onerror="alert(1)" src="invalid">'],
            'onmouseover event' => ['<div onmouseover="alert(1)">Hover</div>'],
            'iframe injection' => ['<iframe src="javascript:alert(1)"></iframe>'],
            'object injection' => ['<object data="javascript:alert(1)"></object>'],
            'embed injection' => ['<embed src="javascript:alert(1)">'],
            'svg script' => ['<svg onload="alert(1)">'],
            'form action javascript' => ['<form action="javascript:alert(1)">'],
            
            // SQL Injection Attacks
            'sql injection basic' => ["'; DROP TABLE users; --"],
            'sql injection union' => ['UNION SELECT * FROM users WHERE 1=1'],
            'sql injection union mixed case' => ['UnIoN sElEcT * fRoM users'],
            'sql injection with comments' => ["'; INSERT INTO admin VALUES ('hacker')--"],
            'sql injection boolean bypass' => ["' OR 1=1 --"],
            'sql injection with AND' => ["' AND (SELECT COUNT(*) FROM users) > 0 --"],
            'sql injection update' => ["'; UPDATE users SET password='hacked' WHERE id=1--"],
            'sql injection delete' => ["'; DELETE FROM logs WHERE id > 0--"],
            'sql injection exec' => ["'; EXEC xp_cmdshell('dir')--"],
            'sql injection execute' => ["'; EXECUTE('DROP TABLE users')--"],
            
            // Command Injection
            'command injection semicolon' => ['test; rm -rf /'],
            'command injection pipe' => ['test | cat /etc/passwd'],
            'command injection ampersand' => ['test & whoami'],
            'command injection backtick' => ['test`id`'],
            'command injection dollar paren' => ['test$(whoami)'],
            'command injection with braces' => ['test{id}'],
            'command injection with brackets' => ['test[id]'],
            
            // Path Traversal
            'path traversal unix' => ['../../etc/passwd'],
            'path traversal windows' => ['..\\..\\windows\\system32\\config\\sam'],
            'path traversal encoded' => ['%2e%2e%2f%2e%2e%2fetc%2fpasswd'],
            'path traversal null byte' => ['../../etc/passwd%00.txt'],
            
            // Code Injection
            'php code' => ['<?php echo "hack"; ?>'],
            'php short tag' => ['<?= system("whoami") ?>'],
            'asp code' => ['<%Response.Write("hack")%>'],
            'jsp code' => ['<%=Runtime.getRuntime().exec("whoami")%>'],
            
            // Additional XSS Vectors
            'data uri xss' => ['<img src="data:text/html,<script>alert(1)</script>">'],
            'javascript entity' => ['&lt;script&gt;alert(1)&lt;/script&gt;'],
            'style expression' => ['<div style="expression(alert(1))">'],
            'link javascript' => ['<link rel="stylesheet" href="javascript:alert(1)">'],
            'meta refresh xss' => ['<meta http-equiv="refresh" content="0;url=javascript:alert(1)">'],
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

    public function testMultipleAttackVectorsInSingleInput(): void
    {
        $constraint = new SafeSurveyText();
        $multipleAttacks = '<script>alert("XSS")</script> AND 1=1; rm -rf /';
        
        $this->context->expects($this->once())
                     ->method('buildViolation')
                     ->willReturn($this->violationBuilder);
                     
        $this->violationBuilder->expects($this->once())
                              ->method('addViolation');

        $this->validator->validate($multipleAttacks, $constraint);
    }

    public function testObfuscatedXSSAttempts(): void
    {
        $constraint = new SafeSurveyText();
        $obfuscatedInputs = [
            '<svg/onload=alert(1)>',
            'javascript:void(0)',
            '<img src=x onerror=alert(1)>',
            '<details open ontoggle=alert(1)>',
        ];
        
        foreach ($obfuscatedInputs as $input) {
            $this->context->expects($this->atLeastOnce())
                         ->method('buildViolation')
                         ->willReturn($this->violationBuilder);
                         
            $this->violationBuilder->expects($this->atLeastOnce())
                                  ->method('addViolation');

            $this->validator->validate($input, $constraint);
        }
    }

    public function testSQLInjectionWithEncodedCharacters(): void
    {
        $constraint = new SafeSurveyText();
        $encodedSqlInputs = [
            "' UNION SELECT NULL--",
            "' OR '1'='1",
            "admin'--",
            "' OR 1=1#",
        ];
        
        foreach ($encodedSqlInputs as $input) {
            $this->context->expects($this->atLeastOnce())
                         ->method('buildViolation')
                         ->willReturn($this->violationBuilder);
                         
            $this->violationBuilder->expects($this->atLeastOnce())
                                  ->method('addViolation');

            $this->validator->validate($input, $constraint);
        }
    }

    public function testBenignContentWithSimilarPatterns(): void
    {
        $constraint = new SafeSurveyText();
        $benignInputs = [
            'I love JavaScript as a programming language',
            'Please select all that apply',  
            'My script for the play is ready',
            'The union of two sets is important',
            'I need to update my profile',
        ];
        
        foreach ($benignInputs as $input) {
            $this->context->expects($this->never())
                         ->method('buildViolation');

            $this->validator->validate($input, $constraint);
        }
    }

    public function testPerformanceWithLargeInput(): void
    {
        $constraint = new SafeSurveyText(['maxLength' => 50000]);
        $largeInput = str_repeat('This is a safe text that should pass validation. ', 1000); // ~47k chars
        
        $startTime = microtime(true);
        
        $this->context->expects($this->never())
                     ->method('buildViolation');

        $this->validator->validate($largeInput, $constraint);
        
        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        // Validation should complete in under 50ms as per requirements
        $this->assertLessThan(50, $duration, 'Validation took too long: ' . $duration . 'ms');
    }

    public function testUnicodeAndSpecialCharactersAreSafe(): void
    {
        $constraint = new SafeSurveyText();
        $unicodeInputs = [
            'Hello 世界! This is safe unicode content.',
            'Special chars: ñáéíóú çüß €£¥',
            'Math symbols: ∑∞∂∆∇√∈∉⊆⊇',
            'Emojis are safe: 😀🎉🚀💯',
        ];
        
        foreach ($unicodeInputs as $input) {
            $this->context->expects($this->never())
                         ->method('buildViolation');

            $this->validator->validate($input, $constraint);
        }
    }
}