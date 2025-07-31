<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Circuit breaker pattern implementation for validation service reliability
 * Provides graceful degradation when validation service fails
 */
class ValidationCircuitBreaker
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';
    
    private const DEFAULT_FAILURE_THRESHOLD = 5;
    private const DEFAULT_RECOVERY_TIMEOUT = 60; // seconds
    private const DEFAULT_TEST_REQUEST_TIMEOUT = 30; // seconds
    private const DEFAULT_MAX_RECOVERY_TIMEOUT = 300; // 5 minutes max
    
    private string $state = self::STATE_CLOSED;
    private int $failureCount = 0;
    private int $lastFailureTime = 0;
    private int $lastSuccessTime = 0;
    private int $consecutiveFailures = 0;
    private array $recentFailures = [];
    
    public function __construct(
        private LoggerInterface $logger,
        private int $failureThreshold = self::DEFAULT_FAILURE_THRESHOLD,
        private int $recoveryTimeout = self::DEFAULT_RECOVERY_TIMEOUT,
        private int $testRequestTimeout = self::DEFAULT_TEST_REQUEST_TIMEOUT,
        private int $maxRecoveryTimeout = self::DEFAULT_MAX_RECOVERY_TIMEOUT
    ) {
    }
    
    /**
     * Execute a callable with circuit breaker protection
     * @param callable $operation The operation to execute
     * @param callable $fallback Fallback operation if circuit is open
     * @return mixed Result of operation or fallback
     */
    public function execute(callable $operation, callable $fallback)
    {
        if ($this->isOpen()) {
            $this->logger->warning('Validation circuit breaker is OPEN, using fallback');
            return $fallback();
        }
        
        if ($this->isHalfOpen()) {
            return $this->executeTestRequest($operation, $fallback);
        }
        
        try {
            $result = $operation();
            $this->onSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->onFailure($e);
            $this->logger->error('Validation service failed, using fallback', [
                'exception' => $e->getMessage(),
                'failure_count' => $this->failureCount
            ]);
            return $fallback();
        }
    }
    
    /**
     * Check if circuit breaker is in closed state (normal operation)
     */
    public function isClosed(): bool
    {
        return $this->state === self::STATE_CLOSED;
    }
    
    /**
     * Check if circuit breaker is in open state (failing fast)
     */
    public function isOpen(): bool
    {
        if ($this->state === self::STATE_OPEN && $this->shouldAttemptReset()) {
            $this->state = self::STATE_HALF_OPEN;
            $this->logger->info('Validation circuit breaker state changed to HALF_OPEN');
            return false;
        }
        
        return $this->state === self::STATE_OPEN;
    }
    
    /**
     * Check if circuit breaker is in half-open state (testing recovery)
     */
    public function isHalfOpen(): bool
    {
        return $this->state === self::STATE_HALF_OPEN;
    }
    
    /**
     * Get current circuit breaker state for monitoring
     */
    public function getState(): string
    {
        return $this->state;
    }
    
    /**
     * Get current failure count for monitoring
     */
    public function getFailureCount(): int
    {
        return $this->failureCount;
    }
    
    /**
     * Manual reset of circuit breaker (for administrative purposes)
     */
    public function reset(): void
    {
        $this->state = self::STATE_CLOSED;
        $this->failureCount = 0;
        $this->logger->info('Validation circuit breaker manually reset to CLOSED');
    }
    
    private function executeTestRequest(callable $operation, callable $fallback)
    {
        try {
            $result = $operation();
            $this->onSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->onFailure($e);
            $this->logger->warning('Validation test request failed, circuit breaker reopened', [
                'exception' => $e->getMessage()
            ]);
            return $fallback();
        }
    }
    
    private function onSuccess(): void
    {
        $this->failureCount = 0;
        $this->consecutiveFailures = 0; // Reset consecutive failures on success
        $this->lastSuccessTime = time();
        
        if ($this->state !== self::STATE_CLOSED) {
            $this->state = self::STATE_CLOSED;
            $this->logger->info('Validation circuit breaker recovered, state changed to CLOSED');
        }
    }
    
    private function onFailure(\Throwable $e): void
    {
        $currentTime = time();
        $this->failureCount++;
        $this->consecutiveFailures++;
        $this->lastFailureTime = $currentTime;
        
        // Track recent failures for health check
        $this->recentFailures[] = $currentTime;
        
        if ($this->failureCount >= $this->failureThreshold) {
            $this->state = self::STATE_OPEN;
            $backoffTimeout = $this->calculateBackoffTimeout();
            
            $this->logger->error('Validation circuit breaker opened due to consecutive failures', [
                'failure_count' => $this->failureCount,
                'consecutive_failures' => $this->consecutiveFailures,
                'threshold' => $this->failureThreshold,
                'backoff_timeout' => $backoffTimeout,
                'last_exception' => $e->getMessage()
            ]);
        }
    }
    
    private function shouldAttemptReset(): bool
    {
        $timeSinceLastFailure = time() - $this->lastFailureTime;
        $requiredTimeout = $this->calculateBackoffTimeout();
        
        if ($timeSinceLastFailure < $requiredTimeout) {
            return false;
        }
        
        // Perform basic health check before attempting reset
        return $this->performHealthCheck();
    }
    
    private function calculateBackoffTimeout(): int
    {
        // Exponential backoff: base timeout * 2^(consecutive_failures - threshold)
        // Capped at maxRecoveryTimeout
        $backoffMultiplier = max(0, $this->consecutiveFailures - $this->failureThreshold);
        $backoffTimeout = $this->recoveryTimeout * (2 ** min($backoffMultiplier, 4)); // Cap at 2^4 = 16x
        
        return min($backoffTimeout, $this->maxRecoveryTimeout);
    }
    
    private function performHealthCheck(): bool
    {
        // Basic health check - check failure rate over recent time window
        $currentTime = time();
        $recentWindow = 300; // 5 minutes
        
        // Clean old failures
        $this->recentFailures = array_filter(
            $this->recentFailures,
            fn($timestamp) => ($currentTime - $timestamp) <= $recentWindow
        );
        
        // If we have too many recent failures, don't attempt reset yet
        $recentFailureCount = count($this->recentFailures);
        $maxRecentFailures = 10; // Allow max 10 failures in 5 minutes
        
        if ($recentFailureCount >= $maxRecentFailures) {
            $this->logger->warning('Circuit breaker health check failed: too many recent failures', [
                'recent_failures' => $recentFailureCount,
                'max_allowed' => $maxRecentFailures,
                'window_seconds' => $recentWindow
            ]);
            return false;
        }
        
        $this->logger->info('Circuit breaker health check passed', [
            'recent_failures' => $recentFailureCount,
            'time_since_last_failure' => $currentTime - $this->lastFailureTime
        ]);
        
        return true;
    }
}