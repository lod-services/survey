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
    
    private string $state = self::STATE_CLOSED;
    private int $failureCount = 0;
    private int $lastFailureTime = 0;
    private int $lastSuccessTime = 0;
    
    public function __construct(
        private LoggerInterface $logger,
        private int $failureThreshold = self::DEFAULT_FAILURE_THRESHOLD,
        private int $recoveryTimeout = self::DEFAULT_RECOVERY_TIMEOUT,
        private int $testRequestTimeout = self::DEFAULT_TEST_REQUEST_TIMEOUT
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
        $this->lastSuccessTime = time();
        
        if ($this->state !== self::STATE_CLOSED) {
            $this->state = self::STATE_CLOSED;
            $this->logger->info('Validation circuit breaker recovered, state changed to CLOSED');
        }
    }
    
    private function onFailure(\Throwable $e): void
    {
        $this->failureCount++;
        $this->lastFailureTime = time();
        
        if ($this->failureCount >= $this->failureThreshold) {
            $this->state = self::STATE_OPEN;
            $this->logger->error('Validation circuit breaker opened due to consecutive failures', [
                'failure_count' => $this->failureCount,
                'threshold' => $this->failureThreshold,
                'last_exception' => $e->getMessage()
            ]);
        }
    }
    
    private function shouldAttemptReset(): bool
    {
        return (time() - $this->lastFailureTime) >= $this->recoveryTimeout;
    }
}