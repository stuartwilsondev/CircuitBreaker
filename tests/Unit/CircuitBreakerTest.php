<?php

declare(strict_types=1);

namespace Stuartwilsondev\CircuitBreaker\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Stuartwilsondev\CircuitBreaker\CircuitBreaker;
use Stuartwilsondev\CircuitBreaker\CircuitBreakerState;
use Stuartwilsondev\CircuitBreaker\CircuitBreakerStorageInterface;
use Stuartwilsondev\CircuitBreaker\Exceptions\HalfOpenCircuitException;
use Stuartwilsondev\CircuitBreaker\Exceptions\OpenCircuitException;
use Stuartwilsondev\CircuitBreaker\Exceptions\OperationExecuteException;
use Stuartwilsondev\CircuitBreaker\ValueObject\OperationInterface;
use Stuartwilsondev\CircuitBreaker\ValueObject\Result;

class CircuitBreakerTest extends TestCase
{
    public function testCircuitBreakerStartsInClosedState(): void
    {
        $storage = $this->createMock(CircuitBreakerStorageInterface::class);
        $storage->method('getState')->willReturn(CircuitBreakerState::CLOSED);
        $storage->method('getFailures')->willReturn(0);

        $circuitBreaker = new CircuitBreaker($storage, 3);

        $operation = $this->createMock(OperationInterface::class);
        $operation->method('execute')->willReturn(Result::success('Success'));

        $storage->expects($this->never())->method('saveState');

        $circuitBreaker->call('test-api', $operation);
    }

    public function testCircuitBreakerTransitionsToOpensStateAfterFailureThreshold(): void
    {
        $storage = $this->createMock(CircuitBreakerStorageInterface::class);
        $storage->method('getState')->willReturn(CircuitBreakerState::CLOSED);
        $storage->method('getFailures')->willReturn(3);

        $circuitBreaker = new CircuitBreaker($storage, 3);

        $this->expectException(OpenCircuitException::class);
        $this->expectExceptionMessage('Circuit is open for "test-api". Latest error: API failed');

        $operation = $this->createMock(OperationInterface::class);
        $operation->method('execute')->willThrowException(new OperationExecuteException("API failed"));

        $circuitBreaker->call('test-api', $operation);
    }


    public function testCircuitBreakerRemainsInOpenState(): void
    {
        $storage = $this->createMock(CircuitBreakerStorageInterface::class);
        $storage->method('getState')->willReturn(CircuitBreakerState::OPEN);

        $circuitBreaker = new CircuitBreaker($storage, 3);

        $this->expectException(OpenCircuitException::class);
        $this->expectExceptionMessage('Circuit is open for "test-api"');

        $operation = $this->createMock(OperationInterface::class);

        $circuitBreaker->call('test-api', $operation);
    }

    public function testCircuitBreakerInHalfOpenState(): void
    {
        $storage = $this->createMock(CircuitBreakerStorageInterface::class);
        $storage->method('getState')->willReturn(CircuitBreakerState::HALF_OPEN);

        $circuitBreaker = new CircuitBreaker($storage, 3);

        $this->expectException(HalfOpenCircuitException::class);
        $this->expectExceptionMessage('Half-open state failed for "test-api". Latest error: Half-Open API call failed');

        $operation = $this->createMock(OperationInterface::class);
        $operation->method('execute')->willThrowException(new OperationExecuteException("Half-Open API call failed"));

        $circuitBreaker->call('test-api', $operation);
    }

    public function testCircuitBreakerTransitionsFromOpenStateToHalfOpenStateAfterTimeout(): void
    {
        $storage = $this->createMock(CircuitBreakerStorageInterface::class);
        $storage->method('getState')->with('test-api')->willReturnOnConsecutiveCalls(
            CircuitBreakerState::OPEN,
            CircuitBreakerState::HALF_OPEN,
            CircuitBreakerState::HALF_OPEN
        );

        $storage->method('getLastOpenDateTime')->willReturn(new DateTimeImmutable('-6 seconds'));

        //Add callback to capture that calls to saveState are made with the expected arguments (asserted later)
        $serviceKey = 'test-api';
        $callArgs = [];
        $storage->method('saveState')->willReturnCallback(function($key, $state) use (&$callArgs): void {
            $callArgs[] = [$key, $state];
        });

        $circuitBreaker = new CircuitBreaker($storage, 3, 5);

        $expectedResult = Result::success('Success');
        $operation = $this->createMock(OperationInterface::class);
        $operation->method('execute')->willReturn($expectedResult);

        $result = $circuitBreaker->call($serviceKey, $operation);
        $this->assertSame($expectedResult, $result);

        $this->assertSame([$serviceKey, CircuitBreakerState::HALF_OPEN], $callArgs[0]);
    }

    public function testHalfOpenStateTransitionsToClosedStateAfterSuccess(): void
    {
        $storage = $this->createMock(CircuitBreakerStorageInterface::class);
        $storage->method('getState')->with('test-api')->willReturnOnConsecutiveCalls(
            CircuitBreakerState::HALF_OPEN,
            CircuitBreakerState::HALF_OPEN,
            CircuitBreakerState::HALF_OPEN);
        $storage->method('getLastOpenDateTime')->willReturn(new DateTimeImmutable('-6 seconds'));

        $circuitBreaker = new CircuitBreaker($storage, 3, 5);

        //Add callback to capture that calls to saveState are made with the expected arguments (asserted later)
        $serviceKey = 'test-api';
        $callArgs = [];
        $storage->method('saveState')->willReturnCallback(function($key, $state) use (&$callArgs): void {
            $callArgs[] = [$key, $state];
        });

        $expectedResult = Result::success('Success');
        $operation = $this->createMock(OperationInterface::class);
        $operation->method('execute')->willReturn($expectedResult);

        $result = $circuitBreaker->call($serviceKey, $operation);

        $this->assertEquals($expectedResult, $result);
        $this->assertSame([$serviceKey, CircuitBreakerState::CLOSED], $callArgs[0]);
    }

    public function testHalfOpenStateFailsAndReopensCircuitByTransitioningToOpenState(): void
    {
        $storage = $this->createMock(CircuitBreakerStorageInterface::class);
        $storage->method('getState')->with('test-api')->willReturnOnConsecutiveCalls(
            CircuitBreakerState::HALF_OPEN,
            CircuitBreakerState::HALF_OPEN,
            CircuitBreakerState::HALF_OPEN);

        $serviceKey = 'test-api';
        $callArgs = [];
        $storage->method('saveState')->willReturnCallback(function($key, $state) use (&$callArgs): void {
            $callArgs[] = [$key, $state];
        });

        $circuitBreaker = new CircuitBreaker($storage, 3, 5);

        $this->expectException(HalfOpenCircuitException::class);
        $this->expectExceptionMessage('Half-open state failed for "test-api". Latest error: Half-Open API call failed');

        $operation = $this->createMock(OperationInterface::class);
        $operation->method('execute')->willThrowException(
            new OperationExecuteException("Half-Open API call failed")
        );

        $circuitBreaker->call($serviceKey, $operation);
        $this->assertSame([$serviceKey, CircuitBreakerState::OPEN], $callArgs[0]);
    }
}