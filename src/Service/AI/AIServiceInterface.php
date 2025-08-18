<?php

namespace App\Service\AI;

interface AIServiceInterface
{
    /**
     * Generate smart question suggestions based on topic and context
     */
    public function generateQuestionSuggestions(string $topic, array $context = []): array;

    /**
     * Analyze text for potential bias and return bias score with suggestions
     */
    public function analyzeBias(string $text): array;

    /**
     * Check text for inclusive language and provide recommendations
     */
    public function checkInclusiveLanguage(string $text): array;

    /**
     * Predict survey completion rate based on structure and content
     */
    public function predictCompletionRate(array $surveyData): array;

    /**
     * Optimize question flow for better engagement
     */
    public function optimizeQuestionFlow(array $questions): array;

    /**
     * Filter content for safety and appropriateness
     */
    public function filterContent(string $content): array;

    /**
     * Check if the AI service is available
     */
    public function isAvailable(): bool;
}