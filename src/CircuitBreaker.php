<?php

declare(strict_types=1);

namespace Stuartwilsondev\CircuitBreaker;

use Stuartwilsondev\CircuitBreaker\Exceptions\HalfOpenCircuitException;
use Stuartwilsondev\CircuitBreaker\Exceptions\OpenCircuitException;
use Exception;
use DateTimeImmutable;

readonly class CircuitBreaker
{
    public function __construct(
        private CircuitBreakerStorageInterface $storage,
        private int                            $failureThreshold = 3,
        private int                            $timeoutInSeconds = 5,
    ) {}

    /**
     * @throws OpenCircuitException
     * @throws HalfOpenCircuitException
     */
    public function call(string $serviceKey, callable $apiCall): mixed
    {
        if($this->storage->getState($serviceKey) === CircuitBreakerState::OPEN) {
            $this->handleOpenToHalfOpen($serviceKey);
        }

        return match ($this->storage->getState($serviceKey)) {
            CircuitBreakerState::OPEN => throw new OpenCircuitException(sprintf('Circuit is open for "%s"', $serviceKey)),
            CircuitBreakerState::HALF_OPEN => $this->handleHalfOpenToClosed($serviceKey, $apiCall),
            default => $this->attemptApiCall($serviceKey, $apiCall)
        };
    }

    private function handleOpenToHalfOpen(string $serviceKey): void
    {
        $lastOpenTime = $this->storage->getLastOpenDateTime($serviceKey);
        if (!$lastOpenTime instanceof DateTimeImmutable) {
            $this->storage->setLastOpenDateTime($serviceKey, new DateTimeImmutable());
            return;
        }

        $now = new DateTimeImmutable();
        $interval = $now->diff($lastOpenTime);
        if ($interval->s >= $this->timeoutInSeconds) {
            $this->storage->saveState($serviceKey, CircuitBreakerState::HALF_OPEN);
        }
    }

    private function handleHalfOpenToClosed(string $serviceKey, callable $apiCall): mixed
    {
        try {
            $result = $apiCall();
            $this->storage->saveState($serviceKey, CircuitBreakerState::CLOSED);
            $this->storage->resetFailures($serviceKey);
            return $result;
        } catch (Exception $exception) {
            $this->storage->saveState($serviceKey, CircuitBreakerState::OPEN);
            $this->storage->setLastOpenDateTime($serviceKey, new DateTimeImmutable());
            throw new HalfOpenCircuitException(sprintf('Half-open state failed for "%s". Latest error: %s', $serviceKey, $exception->getMessage()));
        }
    }

    private function attemptApiCall(string $serviceKey, callable $apiCall): mixed
    {
        try {
            $result = $apiCall();
            $this->storage->resetFailures($serviceKey);
            return $result;
        } catch (Exception $exception) {
            $this->storage->incrementFailures($serviceKey);
            $failures = $this->storage->getFailures($serviceKey);

            if ($failures >= $this->failureThreshold) {
                $this->storage->saveState($serviceKey, CircuitBreakerState::OPEN);
                $this->storage->setLastOpenDateTime($serviceKey, new DateTimeImmutable());

                throw new OpenCircuitException(sprintf('Circuit is open for "%s". Latest error: %s', $serviceKey, $exception->getMessage()));
            }
        }

        throw $exception;
    }
}