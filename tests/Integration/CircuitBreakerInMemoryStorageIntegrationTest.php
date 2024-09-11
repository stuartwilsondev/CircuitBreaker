<?php

declare(strict_types=1);

namespace Stuartwilsondev\CircuitBreaker\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Stuartwilsondev\CircuitBreaker\CircuitBreaker;
use Stuartwilsondev\CircuitBreaker\CircuitBreakerState;
use Stuartwilsondev\CircuitBreaker\Exceptions\OpenCircuitException;
use Stuartwilsondev\CircuitBreaker\InMemoryCircuitBreakerStorage;
use Exception;

class CircuitBreakerInMemoryStorageIntegrationTest extends TestCase
{

    public function testCircuitBreakerWorksWithInMemoryStorage(): void
    {
        $storage = new InMemoryCircuitBreakerStorage();
        $circuitBreaker = new CircuitBreaker($storage, 3);

        // Test successful API call
        $result = $circuitBreaker->call('test-api', fn(): string => 'Success');
        $this->assertEquals('Success', $result);

        // Test failures and circuit opening
        for ($i = 0; $i < 3; $i++) {
            try {
                $circuitBreaker->call('test-api', fn() => throw new Exception("API failed"));
            } catch (Exception $e) {
                // Ignore exception for first 3 failures
            }
        }

        // Test that the circuit is now open
        $this->expectException(OpenCircuitException::class);
        $this->expectExceptionMessage('Circuit is open for "test-api');
        $circuitBreaker->call('test-api', fn(): string => 'This call should not happen');
    }

    public function testCircuitBreakerTransitionsToHalfOpenAfterTimeout(): void
    {
        $storage = new InMemoryCircuitBreakerStorage();
        $circuitBreaker = new CircuitBreaker($storage, 3, 1);

        // Test failures and circuit opening
        for ($i = 0; $i < 3; $i++) {
            try {
                $circuitBreaker->call('test-api', fn() => throw new Exception("API failed"));
            } catch (Exception $e) {
                // Ignore exception for first 3 failures
            }
        }

        // Test that the circuit is now open
        $this->expectException(OpenCircuitException::class);
        $this->expectExceptionMessage('Circuit is open for "test-api');
        $circuitBreaker->call('test-api', fn(): string => 'This call should not happen');
        $this->assertEquals(CircuitBreakerState::OPEN, $storage->getState('test-api'));

        // Wait for the circuit to transition to half-open
        sleep(2);

        // Test that the circuit is now half-open
        $result = $circuitBreaker->call('test-api', fn(): string => 'Success');
        $this->assertEquals(CircuitBreakerState::HALF_OPEN, $storage->getState('test-api'));
        $this->assertEquals('Success', $result);
    }
}