<?php

declare(strict_types=1);

namespace Stuartwilsondev\CircuitBreaker;

use DateTimeImmutable;

class InMemoryCircuitBreakerStorage implements CircuitBreakerStorageInterface
{
    /**
     * @param array<string, CircuitBreakerState> $states
     * @param array<string, int> $failures
     * @param array<string, DateTimeImmutable> $lastOpenTimes
     */
    public function __construct(
        private array $states = [],
        private array $failures = [],
        private array $lastOpenTimes = [],
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

    public function setLastOpenDateTime(string $key, DateTimeImmutable $lastOpenDatetime): void
    {
        $this->lastOpenTimes[$key] = $lastOpenDatetime;
    }

    public function getLastOpenDateTime(string $key): ?DateTimeImmutable
    {
        return $this->lastOpenTimes[$key] ?? null;
    }
}


