<?php

declare(strict_types=1);

namespace Stuartwilsondev\CircuitBreaker;

interface CircuitBreakerStorageInterface
{
    public function saveState(string $key, CircuitBreakerState $state): void;

    public function getState(string $key): CircuitBreakerState;

    public function incrementFailures(string $key): void;

    public function getFailures(string $key): int;

    public function resetFailures(string $key): void;
}