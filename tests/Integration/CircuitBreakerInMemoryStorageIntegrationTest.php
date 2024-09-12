<?php

declare(strict_types=1);

namespace Stuartwilsondev\CircuitBreaker\Tests\Integration;

use Exception;
use PHPUnit\Framework\TestCase;
use Stuartwilsondev\CircuitBreaker\CircuitBreaker;
use Stuartwilsondev\CircuitBreaker\CircuitBreakerState;
use Stuartwilsondev\CircuitBreaker\Exceptions\OpenCircuitException;
use Stuartwilsondev\CircuitBreaker\Exceptions\OperationExecuteException;
use Stuartwilsondev\CircuitBreaker\InMemoryCircuitBreakerStorage;
use Stuartwilsondev\CircuitBreaker\ValueObject\OperationInterface;
use Stuartwilsondev\CircuitBreaker\ValueObject\Result;

class CircuitBreakerInMemoryStorageIntegrationTest extends TestCase
{
    public function testCircuitBreakerWorksWithInMemoryStorage(): void
    {
        $storage = new InMemoryCircuitBreakerStorage();
        $circuitBreaker = new CircuitBreaker($storage, 3);

        $operation = $this->createMock(OperationInterface::class);
        $operation->method('execute')->willReturn(Result::success('Success'));

        $result = $circuitBreaker->call('test-api', $operation);
        $this->assertEquals(Result::success('Success'), $result);

        $failingOperation = $this->createMock(OperationInterface::class);
        $failingOperation->method('execute')->willThrowException(new OperationExecuteException("API failed"));

        // Test failures and circuit opening
        for ($i = 0; $i < 3; $i++) {
            try {
                $circuitBreaker->call('test-api', $failingOperation);
            } catch (Exception $e) {
                // Ignore exception for first 3 failures
            }
        }

        // Test that the circuit is now open
        $this->expectException(OpenCircuitException::class);
        $this->expectExceptionMessage('Circuit is open for "test-api');
        $circuitBreaker->call('test-api', $operation);
    }

    public function testCircuitBreakerTransitionsToHalfOpenAfterTimeout(): void
    {
        $storage = new InMemoryCircuitBreakerStorage();
        $circuitBreaker = new CircuitBreaker($storage, 3, 1);

        $failingOperation = $this->createMock(OperationInterface::class);
        $failingOperation->method('execute')->willThrowException(new OperationExecuteException("API failed"));
        // Test failures and circuit opening
        for ($i = 0; $i < 3; $i++) {
            try {
                $circuitBreaker->call('test-api', $failingOperation);
            } catch (Exception $e) {
                // Ignore exception for first 3 failures
            }
        }

        // Test that the circuit is now open
        $this->expectException(OpenCircuitException::class);
        $this->expectExceptionMessage('Circuit is open for "test-api');
        $circuitBreaker->call('test-api', $this->createMock(OperationInterface::class));
        $this->assertEquals(CircuitBreakerState::OPEN, $storage->getState('test-api'));

        // Wait for the circuit to transition to half-open
        sleep(2);

        $operation = $this->createMock(OperationInterface::class);
        $operation->method('getResult')->willReturn('Success');
        $operation->method('execute')->willReturn('Success');

        // Test that the circuit is now half-open
        $result = $circuitBreaker->call('test-api', $operation);
        $this->assertEquals(CircuitBreakerState::HALF_OPEN, $storage->getState('test-api'));
        $this->assertEquals('Success', $result);
    }
}