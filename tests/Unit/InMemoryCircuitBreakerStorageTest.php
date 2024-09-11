<?php

declare(strict_types=1);

namespace Stuartwilsondev\CircuitBreaker\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stuartwilsondev\CircuitBreaker\CircuitBreakerState;
use Stuartwilsondev\CircuitBreaker\InMemoryCircuitBreakerStorage;
use DateTimeImmutable;

class InMemoryCircuitBreakerStorageTest extends TestCase
{

    public function testSaveAndRetrieveState(): void
    {
        $storage = new InMemoryCircuitBreakerStorage();
        $serviceName = 'test-api';

        $storage->saveState($serviceName, CircuitBreakerState::CLOSED);
        $this->assertEquals(CircuitBreakerState::CLOSED, $storage->getState($serviceName));

        $storage->saveState($serviceName, CircuitBreakerState::OPEN);
        $this->assertEquals(CircuitBreakerState::OPEN, $storage->getState($serviceName));
    }

    public function testIncrementAndGetFailures(): void
    {
        $storage = new InMemoryCircuitBreakerStorage();
        $serviceName = 'test-api';

        // Initially, no failures
        $this->assertEquals(0, $storage->getFailures($serviceName));

        // Increment failures assert count increases
        $storage->incrementFailures($serviceName);
        $this->assertEquals(1, $storage->getFailures($serviceName));

        // Increment failures assert count increases
        $storage->incrementFailures($serviceName);
        $this->assertEquals(2, $storage->getFailures($serviceName));
    }

    public function testResetFailures(): void
    {
        $storage = new InMemoryCircuitBreakerStorage();
        $serviceName = 'test-api';

        // Increment failures
        $storage->incrementFailures($serviceName);
        $storage->incrementFailures($serviceName);
        $this->assertEquals(2, $storage->getFailures($serviceName));

        // Reset failures and assert zero
        $storage->resetFailures($serviceName);
        $this->assertEquals(0, $storage->getFailures($serviceName));
    }

    public function testGetStateForUnknownKeyReturnsClosedState(): void
    {
        $storage = new InMemoryCircuitBreakerStorage();
        $this->assertEquals(CircuitBreakerState::CLOSED, $storage->getState('unknown-api'));
    }

    public function testResetOnUnknownServiceReturnsGracefully(): void
    {
        $storage = new InMemoryCircuitBreakerStorage();
        $serviceName = 'unknown-api';

        $storage->resetFailures($serviceName);
        $this->assertEquals(0, $storage->getFailures($serviceName));
    }

    public function testSetAndGetLastOpenDateTime(): void
    {
        $storage = new InMemoryCircuitBreakerStorage();
        $serviceName = 'test-api';

        $lastOpenDatetime = $storage->getLastOpenDateTime($serviceName);
        $this->assertNull($lastOpenDatetime);

        $newLastOpenDatetime = new DateTimeImmutable();
        $storage->setLastOpenDateTime($serviceName, $newLastOpenDatetime);
        $this->assertEquals($newLastOpenDatetime, $storage->getLastOpenDateTime($serviceName));
    }
}