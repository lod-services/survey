<?php

namespace App\Tests\Service;

use App\Entity\Survey;
use App\Service\AiQuestionGeneratorService;
use OpenAI\Client;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Cache\CacheInterface;

class AiQuestionGeneratorServiceTest extends KernelTestCase
{
    public function testFallbackQuestionsAreGenerated(): void
    {
        // Mock dependencies
        $mockClient = $this->createMock(Client::class);
        $mockCache = $this->createMock(CacheInterface::class);
        $mockLogger = $this->createMock(LoggerInterface::class);

        // Configure cache to throw exception to trigger fallback
        $mockCache->method('get')->willThrowException(new \Exception('Cache failure'));

        $service = new AiQuestionGeneratorService($mockClient, $mockCache, $mockLogger);
        
        $survey = new Survey();
        $survey->setTitle('Test Survey');
        $survey->setObjective('Test customer satisfaction');
        
        $questions = $service->generateQuestions($survey);
        
        $this->assertIsArray($questions);
        $this->assertNotEmpty($questions);
        $this->assertGreaterThanOrEqual(3, count($questions));
        
        // Check that fallback questions have the required structure
        foreach ($questions as $question) {
            $this->assertArrayHasKey('text', $question);
            $this->assertArrayHasKey('type', $question);
            $this->assertArrayHasKey('rationale', $question);
            $this->assertArrayHasKey('confidence', $question);
            $this->assertArrayHasKey('metadata', $question);
            
            $this->assertIsString($question['text']);
            $this->assertIsFloat($question['confidence']);
            $this->assertIsArray($question['metadata']);
            $this->assertArrayHasKey('ai_generated', $question['metadata']);
            $this->assertFalse($question['metadata']['ai_generated']); // Fallback questions are not AI generated
        }
    }

    public function testCreateAiSuggestions(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockCache = $this->createMock(CacheInterface::class);
        $mockLogger = $this->createMock(LoggerInterface::class);

        $service = new AiQuestionGeneratorService($mockClient, $mockCache, $mockLogger);
        
        $survey = new Survey();
        $survey->setTitle('Test Survey');
        
        $questionSuggestions = [
            [
                'text' => 'How satisfied are you with our service?',
                'type' => 'scale',
                'rationale' => 'Measures overall satisfaction',
                'confidence' => 0.85,
                'metadata' => ['test' => true]
            ]
        ];
        
        $suggestions = $service->createAiSuggestions($survey, $questionSuggestions);
        
        $this->assertIsArray($suggestions);
        $this->assertCount(1, $suggestions);
        
        $suggestion = $suggestions[0];
        $this->assertEquals($survey, $suggestion->getSurvey());
        $this->assertEquals('How satisfied are you with our service?', $suggestion->getSuggestedContent());
        $this->assertEquals(0.85, $suggestion->getConfidenceScore());
        $this->assertEquals('Measures overall satisfaction', $suggestion->getRationale());
        $this->assertEquals(['test' => true], $suggestion->getMetadata());
    }
}