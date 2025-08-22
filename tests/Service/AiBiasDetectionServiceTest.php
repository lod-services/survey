<?php

namespace App\Tests\Service;

use App\Entity\Survey;
use App\Service\AiBiasDetectionService;
use OpenAI\Client;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Cache\CacheInterface;

class AiBiasDetectionServiceTest extends KernelTestCase
{
    public function testBasicBiasDetection(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockCache = $this->createMock(CacheInterface::class);
        $mockLogger = $this->createMock(LoggerInterface::class);

        // Configure cache to throw exception to use basic bias detection
        $mockCache->method('get')->willThrowException(new \Exception('Cache failure'));

        $service = new AiBiasDetectionService($mockClient, $mockCache, $mockLogger);
        
        // Test leading question
        $leadingQuestion = "Don't you think our product is amazing?";
        $result = $service->analyzeBias($leadingQuestion);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('bias_detected', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('issues', $result);
        
        $this->assertTrue($result['bias_detected']);
        $this->assertContains('leading', $result['bias_types']);
    }

    public function testNeutralQuestionAnalysis(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockCache = $this->createMock(CacheInterface::class);
        $mockLogger = $this->createMock(LoggerInterface::class);

        // Configure cache to throw exception to use basic bias detection
        $mockCache->method('get')->willThrowException(new \Exception('Cache failure'));

        $service = new AiBiasDetectionService($mockClient, $mockCache, $mockLogger);
        
        // Test neutral question
        $neutralQuestion = "How would you rate our customer service?";
        $result = $service->analyzeBias($neutralQuestion);
        
        $this->assertIsArray($result);
        $this->assertFalse($result['bias_detected']);
        $this->assertGreaterThan(0.5, $result['confidence']);
    }

    public function testCreateBiasSuggestion(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockCache = $this->createMock(CacheInterface::class);
        $mockLogger = $this->createMock(LoggerInterface::class);

        $service = new AiBiasDetectionService($mockClient, $mockCache, $mockLogger);
        
        $survey = new Survey();
        $survey->setTitle('Test Survey');
        
        $originalQuestion = "Don't you think our service is excellent?";
        $biasAnalysis = [
            'bias_detected' => true,
            'confidence' => 0.8,
            'bias_types' => ['leading'],
            'issues' => [
                [
                    'type' => 'leading',
                    'description' => 'Leading question detected',
                    'severity' => 'high'
                ]
            ],
            'suggestions' => [
                [
                    'improved_text' => 'How would you rate our service?',
                    'rationale' => 'Neutral phrasing allows for unbiased responses'
                ]
            ]
        ];
        
        $suggestion = $service->createBiasSuggestion($survey, $originalQuestion, $biasAnalysis);
        
        $this->assertNotNull($suggestion);
        $this->assertEquals($survey, $suggestion->getSurvey());
        $this->assertEquals($originalQuestion, $suggestion->getOriginalContent());
        $this->assertEquals('How would you rate our service?', $suggestion->getSuggestedContent());
        $this->assertEquals(0.8, $suggestion->getConfidenceScore());
        $this->assertEquals('bias_detection', $suggestion->getSuggestionType());
    }

    public function testShouldFlagQuestion(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockCache = $this->createMock(CacheInterface::class);
        $mockLogger = $this->createMock(LoggerInterface::class);

        $service = new AiBiasDetectionService($mockClient, $mockCache, $mockLogger);
        
        // High confidence bias should be flagged
        $highBiasAnalysis = [
            'bias_detected' => true,
            'confidence' => 0.8
        ];
        $this->assertTrue($service->shouldFlagQuestion($highBiasAnalysis));
        
        // Low confidence bias should not be flagged
        $lowBiasAnalysis = [
            'bias_detected' => true,
            'confidence' => 0.3
        ];
        $this->assertFalse($service->shouldFlagQuestion($lowBiasAnalysis));
        
        // No bias should not be flagged
        $noBiasAnalysis = [
            'bias_detected' => false,
            'confidence' => 0.9
        ];
        $this->assertFalse($service->shouldFlagQuestion($noBiasAnalysis));
    }
}