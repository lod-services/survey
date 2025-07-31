<?php

namespace App\Tests\Service;

use App\Service\ValidationCircuitBreaker;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ValidationCircuitBreakerTest extends TestCase
{
    private ValidationCircuitBreaker $circuitBreaker;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->circuitBreaker = new ValidationCircuitBreaker(
            $this->logger,
            3, // failure threshold
            1, // recovery timeout in seconds
            1  // test request timeout
        );
    }

    public function testCircuitBreakerStartsInClosedState(): void
    {
        $this->assertTrue($this->circuitBreaker->isClosed());
        $this->assertFalse($this->circuitBreaker->isOpen());
        $this->assertFalse($this->circuitBreaker->isHalfOpen());
        $this->assertEquals('closed', $this->circuitBreaker->getState());
        $this->assertEquals(0, $this->circuitBreaker->getFailureCount());
    }

    public function testSuccessfulOperationKeepsCircuitClosed(): void
    {
        $result = $this->circuitBreaker->execute(
            fn() => 'success',
            fn() => 'fallback'
        );

        $this->assertEquals('success', $result);
        $this->assertTrue($this->circuitBreaker->isClosed());
        $this->assertEquals(0, $this->circuitBreaker->getFailureCount());
    }

    public function testFailedOperationUsesFallback(): void
    {
        $result = $this->circuitBreaker->execute(
            fn() => throw new \Exception('Operation failed'),
            fn() => 'fallback'
        );

        $this->assertEquals('fallback', $result);
        $this->assertTrue($this->circuitBreaker->isClosed()); // Still closed after 1 failure
        $this->assertEquals(1, $this->circuitBreaker->getFailureCount());
    }

    public function testCircuitOpensAfterThresholdFailures(): void
    {
        // Cause 3 failures to reach threshold
        for ($i = 0; $i < 3; $i++) {
            $this->circuitBreaker->execute(
                fn() => throw new \Exception('Operation failed'),
                fn() => 'fallback'
            );
        }

        $this->assertTrue($this->circuitBreaker->isOpen());
        $this->assertEquals(3, $this->circuitBreaker->getFailureCount());
    }

    public function testOpenCircuitUsesFallbackImmediately(): void
    {
        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            $this->circuitBreaker->execute(
                fn() => throw new \Exception('Operation failed'),
                fn() => 'fallback'
            );
        }

        // Next operation should use fallback immediately
        $operationCalled = false;
        $result = $this->circuitBreaker->execute(
            function() use (&$operationCalled) {
                $operationCalled = true;
                return 'success';
            },
            fn() => 'fallback'
        );

        $this->assertEquals('fallback', $result);
        $this->assertFalse($operationCalled);
    }

    public function testCircuitTransitionsToHalfOpenAfterTimeout(): void
    {
        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            $this->circuitBreaker->execute(
                fn() => throw new \Exception('Operation failed'),
                fn() => 'fallback'
            );
        }

        $this->assertTrue($this->circuitBreaker->isOpen());

        // Wait for recovery timeout (mocked by sleeping)
        sleep(2);

        // Next call should transition to half-open
        $this->assertFalse($this->circuitBreaker->isOpen()); // This triggers the transition
    }

    public function testSuccessfulTestRequestClosesCircuit(): void
    {
        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            $this->circuitBreaker->execute(
                fn() => throw new \Exception('Operation failed'),
                fn() => 'fallback'
            );
        }

        // Wait for recovery timeout
        sleep(2);

        // Successful test request should close the circuit
        $result = $this->circuitBreaker->execute(
            fn() => 'success',
            fn() => 'fallback'
        );

        $this->assertEquals('success', $result);
        $this->assertTrue($this->circuitBreaker->isClosed());
        $this->assertEquals(0, $this->circuitBreaker->getFailureCount());
    }

    public function testFailedTestRequestReopensCircuit(): void
    {
        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            $this->circuitBreaker->execute(
                fn() => throw new \Exception('Operation failed'),
                fn() => 'fallback'
            );
        }

        // Wait for recovery timeout
        sleep(2);

        // Failed test request should reopen the circuit
        $result = $this->circuitBreaker->execute(
            fn() => throw new \Exception('Still failing'),
            fn() => 'fallback'
        );

        $this->assertEquals('fallback', $result);
        $this->assertTrue($this->circuitBreaker->isOpen());
    }

    public function testManualResetClosesCircuit(): void
    {
        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            $this->circuitBreaker->execute(
                fn() => throw new \Exception('Operation failed'),
                fn() => 'fallback'
            );
        }

        $this->assertTrue($this->circuitBreaker->isOpen());

        // Manual reset
        $this->circuitBreaker->reset();

        $this->assertTrue($this->circuitBreaker->isClosed());
        $this->assertEquals(0, $this->circuitBreaker->getFailureCount());
    }

    public function testLoggerIsCalledOnStateChanges(): void
    {
        $this->logger->expects($this->atLeastOnce())
                    ->method('error');

        // Cause failures to trigger state change logging
        for ($i = 0; $i < 3; $i++) {
            $this->circuitBreaker->execute(
                fn() => throw new \Exception('Operation failed'),
                fn() => 'fallback'
            );
        }
    }

    public function testFallbackIsCalledOnFailure(): void
    {
        $fallbackCalled = false;
        
        $this->circuitBreaker->execute(
            fn() => throw new \Exception('Operation failed'),
            function() use (&$fallbackCalled) {
                $fallbackCalled = true;
                return 'fallback';
            }
        );

        $this->assertTrue($fallbackCalled);
    }
}