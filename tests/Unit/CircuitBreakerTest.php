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
use DateTimeImmutable;

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

    public function testCircuitBreakerTransitionsToOpensStateAfterFailureThreshold(): void
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

    public function testCircuitBreakerRemainsInOpenState(): void
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
        $this->expectExceptionMessage('Half-open state failed for "test-api". Latest error: Half-Open API call failed');

        $circuitBreaker->call('test-api', function (): void {
            throw new Exception("Half-Open API call failed");
        });
    }

    public function testCircuitBreakerTransitionsFromOpenStateToHalfOpenStateAfterTimeout(): void
    {
        $storage = $this->createMock(CircuitBreakerStorageInterface::class);
        $storage->method('getState')->with('test-api')->willReturnOnConsecutiveCalls(
            CircuitBreakerState::OPEN,
            CircuitBreakerState::HALF_OPEN);

        $storage->method('getLastOpenDateTime')->willReturn(new DateTimeImmutable('-6 seconds'));

        //Add callback to capture that calls to saveState are made with the expected arguments (asserted later)
        $serviceKey = 'test-api';
        $callArgs = [];
        $storage->method('saveState')
            ->willReturnCallback(function($key, $state) use (&$callArgs): void {
                $callArgs[] = [$key, $state];
            });

        $circuitBreaker = new CircuitBreaker($storage, 3, 5);

        $circuitBreaker->call($serviceKey, function (): string {
            return 'Success';
        });

        // Assert that the calls to saveState were made with the expected arguments in the correct order
        $this->assertSame([$serviceKey, CircuitBreakerState::HALF_OPEN], $callArgs[0]);
    }

    public function testHalfOpenStateTransitionsToClosedStateAfterSuccess(): void
    {
        $storage = $this->createMock(CircuitBreakerStorageInterface::class);
        $storage->method('getState')->with('test-api')->willReturnOnConsecutiveCalls(
            CircuitBreakerState::HALF_OPEN,
            CircuitBreakerState::HALF_OPEN);
        $storage->method('getLastOpenDateTime')->willReturn(new DateTimeImmutable('-6 seconds'));

        $circuitBreaker = new CircuitBreaker($storage, 3, 5); // Timeout is set to 5 seconds

        //Add callback to capture that calls to saveState are made with the expected arguments (asserted later)
        $serviceKey = 'test-api';
        $callArgs = [];
        $storage->method('saveState')
            ->willReturnCallback(function($key, $state) use (&$callArgs): void {
                $callArgs[] = [$key, $state];
            });

        $circuitBreaker->call($serviceKey, function (): string {
            return 'Success';
        });

        // Assert that the calls to saveState were made with the expected arguments in the correct order
        $this->assertSame([$serviceKey, CircuitBreakerState::CLOSED], $callArgs[0]);
    }

    public function testHalfOpenStateFailsAndReopensCircuitByTransitioningToOpenState(): void
    {
        $storage = $this->createMock(CircuitBreakerStorageInterface::class);
        $storage->method('getState')->with('test-api')->willReturnOnConsecutiveCalls(
            CircuitBreakerState::HALF_OPEN,
            CircuitBreakerState::HALF_OPEN);

        $circuitBreaker = new CircuitBreaker($storage, 3, 5); // Timeout is set to 5 seconds

        //Add callback to capture that calls to saveState are made with the expected arguments (asserted later)
        $serviceKey = 'test-api';
        $callArgs = [];
        $storage->method('saveState')
            ->willReturnCallback(function($key, $state) use (&$callArgs): void {
                $callArgs[] = [$key, $state];
            });

        $this->expectException(HalfOpenCircuitException::class);
        $this->expectExceptionMessage('Half-open state failed for "test-api". Latest error: Half-Open API call failed');

        $circuitBreaker->call($serviceKey, function (): string {
            throw new Exception("Half-Open API call failed");
        });

        // Assert that the calls to saveState were made with the expected arguments in the correct order
        $this->assertSame([$serviceKey, CircuitBreakerState::OPEN], $callArgs[0]);
    }
}