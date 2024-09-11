<?php

declare(strict_types=1);

namespace Stuartwilsondev\CircuitBreaker;

class InMemoryCircuitBreakerStorage implements CircuitBreakerStorageInterface
{
    /**
     * @param array<string, CircuitBreakerState> $states
     * @param array<string, int> $failures
     */
    public function __construct(
        private array $states = [],
        private array $failures = []
    ) {}

    public function saveState(string $key, CircuitBreakerState $state): void
    {
        $this->states[$key] = $state;
    }

    public function getState(string $key): CircuitBreakerState
    {
        return $this->states[$key] ?? CircuitBreakerState::CLOSED;
    }

    public function incrementFailures(string $key): void
    {
        $this->failures[$key] = ($this->failures[$key] ?? 0) + 1;
    }

    public function getFailures(string $key): int
    {
        return $this->failures[$key] ?? 0;
    }

    public function resetFailures(string $key): void
    {
        $this->failures[$key] = 0;
    }
}


