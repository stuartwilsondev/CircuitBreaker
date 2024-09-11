<?php

declare(strict_types=1);

namespace Stuartwilsondev\CircuitBreaker\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stuartwilsondev\CircuitBreaker\CircuitBreaker;
use Stuartwilsondev\CircuitBreaker\CircuitBreakerState;
use Stuartwilsondev\CircuitBreaker\CircuitBreakerStorageInterface;
use Stuartwilsondev\CircuitBreaker\Exceptions\HalfOpenCircuitException;
use Stuartwilsondev\CircuitBreaker\Exceptions\OpenCircuitException;
use Exception;

class CircuitBreakerTest extends TestCase
{
    public function testCircuitBreakerStartsInClosedState(): void
    {
        $storage = $this->createMock(CircuitBreakerStorageInterface::class);

        $storage->method('getState')->willReturn(CircuitBreakerState::CLOSED);
        $storage->method('getFailures')->willReturn(0);

        $circuitBreaker = new CircuitBreaker($storage, 3);

        $result = $circuitBreaker->call('test-api', function (): string {
            return 'Success';
        });

        $this->assertEquals('Success', $result);
    }

    public function testCircuitBreakerOpensAfterFailureThreshold(): void
    {

        $storage = $this->createMock(CircuitBreakerStorageInterface::class);

        // Circuit breaker starts in closed state and reaches failure threshold
        $storage->method('getState')->willReturn(CircuitBreakerState::CLOSED);
        $storage->method('getFailures')->willReturn(3);

        $circuitBreaker = new CircuitBreaker($storage, 3);
        $this->expectException(OpenCircuitException::class);
        $this->expectExceptionMessage('Circuit is open for "test-api". Latest error: API failed');

        $circuitBreaker->call('test-api', function (): void {
            throw new Exception("API failed");
        });
    }

    public function testCircuitBreakerRemainsOpen(): void
    {
        $storage = $this->createMock(CircuitBreakerStorageInterface::class);

        $storage->method('getState')->willReturn(CircuitBreakerState::OPEN);
        $circuitBreaker = new CircuitBreaker($storage, 3);

        // Simulate an API call and expect the circuit breaker to block it
        $this->expectException(OpenCircuitException::class);
        $this->expectExceptionMessage('Circuit is open for "test-api"');

        $circuitBreaker->call('test-api', function (): string {
            return 'Success';
        });
    }

    public function testCircuitBreakerInHalfOpenState(): void
    {
        $storage = $this->createMock(CircuitBreakerStorageInterface::class);

        $storage->method('getState')->willReturn(CircuitBreakerState::HALF_OPEN);
        $circuitBreaker = new CircuitBreaker($storage, 3);

        // Simulate API call in half-open state
        $this->expectException(HalfOpenCircuitException::class);
        $this->expectExceptionMessage('Circuit is half-open for "test-api"');

        $circuitBreaker->call('test-api', function (): void {
            throw new Exception("Half-Open API call failed");
        });
    }
}