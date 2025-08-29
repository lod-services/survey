<?php

namespace App\Service;

use App\Entity\SurveyResponse;

class PointsCalculatorService
{
    public function calculatePointsForResponse(SurveyResponse $response): int
    {
        if (!$response->isCompleted()) {
            return 0;
        }

        $survey = $response->getSurvey();
        $basePoints = $survey->getBasePoints();
        
        // Start with base points
        $totalPoints = $basePoints;
        
        // Apply completion percentage bonus
        $completionBonus = $this->calculateCompletionBonus($response);
        $totalPoints += $completionBonus;
        
        // Apply time spent bonus/penalty
        $timeBonus = $this->calculateTimeBonus($response);
        $totalPoints += $timeBonus;
        
        // Apply quality score multiplier
        $qualityMultiplier = $this->calculateQualityMultiplier($response);
        $totalPoints = (int) ($totalPoints * $qualityMultiplier);
        
        // Ensure minimum of 1 point for completed surveys
        return max(1, $totalPoints);
    }

    private function calculateCompletionBonus(SurveyResponse $response): int
    {
        $completionRate = $response->getCompletionPercentage() / 100;
        
        // Bonus points for high completion rates
        if ($completionRate >= 1.0) {
            return 5; // Perfect completion bonus
        } elseif ($completionRate >= 0.9) {
            return 3; // Near-perfect completion
        } elseif ($completionRate >= 0.8) {
            return 1; // Good completion
        }
        
        return 0;
    }

    private function calculateTimeBonus(SurveyResponse $response): int
    {
        $timeSpent = $response->getTimeSpentSeconds();
        $minimumTime = $response->getSurvey()->getMinimumTimeSeconds();
        
        if (!$timeSpent || !$minimumTime) {
            return 0;
        }
        
        // Penalty for rushing through
        if ($timeSpent < $minimumTime) {
            return -2; // Penalty for too fast completion
        }
        
        // Sweet spot bonus (1.2x to 3x minimum time)
        $optimalMinTime = $minimumTime * 1.2;
        $optimalMaxTime = $minimumTime * 3;
        
        if ($timeSpent >= $optimalMinTime && $timeSpent <= $optimalMaxTime) {
            return 2; // Thoughtful completion bonus
        }
        
        return 0;
    }

    private function calculateQualityMultiplier(SurveyResponse $response): float
    {
        $qualityScore = $response->getQualityScore();
        
        if ($qualityScore === null) {
            // Calculate basic quality score
            $qualityScore = $this->calculateBasicQualityScore($response);
            $response->setQualityScore($qualityScore);
        }
        
        $survey = $response->getSurvey();
        $baseMultiplier = $survey->getQualityMultiplier() / 10; // Convert to decimal
        
        // Apply quality-based multiplier
        if ($qualityScore >= 0.9) {
            return 1.0 + ($baseMultiplier * 2); // Excellent quality
        } elseif ($qualityScore >= 0.7) {
            return 1.0 + $baseMultiplier; // Good quality
        } elseif ($qualityScore >= 0.5) {
            return 1.0; // Average quality
        } else {
            return 0.8; // Below average quality
        }
    }

    private function calculateBasicQualityScore(SurveyResponse $response): float
    {
        $responseData = $response->getResponseData();
        
        if (empty($responseData)) {
            return 0.0;
        }
        
        $qualityFactors = [];
        
        // Factor 1: Completion percentage
        $qualityFactors[] = min($response->getCompletionPercentage() / 100, 1.0);
        
        // Factor 2: Response diversity (not all same answers)
        $qualityFactors[] = $this->calculateResponseDiversity($responseData);
        
        // Factor 3: Text response quality (for text fields)
        $qualityFactors[] = $this->calculateTextResponseQuality($responseData);
        
        // Factor 4: Time spent appropriateness
        $qualityFactors[] = $this->calculateTimeAppropriatenessScore($response);
        
        // Calculate weighted average
        return array_sum($qualityFactors) / count($qualityFactors);
    }

    private function calculateResponseDiversity(array $responseData): float
    {
        $values = array_values($responseData);
        $numericValues = array_filter($values, 'is_numeric');
        
        if (count($numericValues) < 2) {
            return 0.8; // Neutral score for non-numeric or single responses
        }
        
        $uniqueValues = array_unique($numericValues);
        $diversityRatio = count($uniqueValues) / count($numericValues);
        
        // Higher diversity is better (but not too high to avoid random clicking)
        return min($diversityRatio * 1.2, 1.0);
    }

    private function calculateTextResponseQuality(array $responseData): float
    {
        $textResponses = array_filter($responseData, function($value) {
            return is_string($value) && strlen(trim($value)) > 0;
        });
        
        if (empty($textResponses)) {
            return 0.8; // Neutral score if no text responses
        }
        
        $totalScore = 0;
        foreach ($textResponses as $text) {
            $text = trim($text);
            $wordCount = str_word_count($text);
            
            // Score based on response length and content
            if ($wordCount >= 10) {
                $totalScore += 1.0; // Detailed response
            } elseif ($wordCount >= 3) {
                $totalScore += 0.8; // Adequate response
            } elseif ($wordCount >= 1) {
                $totalScore += 0.6; // Minimal response
            } else {
                $totalScore += 0.2; // Very short or empty
            }
        }
        
        return min($totalScore / count($textResponses), 1.0);
    }

    private function calculateTimeAppropriatenessScore(SurveyResponse $response): float
    {
        $timeSpent = $response->getTimeSpentSeconds();
        $minimumTime = $response->getSurvey()->getMinimumTimeSeconds();
        
        if (!$timeSpent || !$minimumTime) {
            return 0.8; // Neutral score if no time data
        }
        
        if ($timeSpent < $minimumTime * 0.5) {
            return 0.3; // Too fast - likely not thoughtful
        } elseif ($timeSpent < $minimumTime) {
            return 0.6; // Fast but potentially acceptable
        } elseif ($timeSpent <= $minimumTime * 5) {
            return 1.0; // Appropriate time range
        } else {
            return 0.7; // Very slow - might indicate distraction
        }
    }

    public function calculateStreakBonus(int $streakDays): int
    {
        if ($streakDays < 2) {
            return 0;
        }
        
        // Bonus points for maintaining streaks
        $bonusPoints = min($streakDays - 1, 10); // Max 10 bonus points
        
        // Extra bonus for milestone streaks
        if ($streakDays >= 30) {
            $bonusPoints += 5; // Monthly streak bonus
        } elseif ($streakDays >= 7) {
            $bonusPoints += 2; // Weekly streak bonus
        }
        
        return $bonusPoints;
    }
}