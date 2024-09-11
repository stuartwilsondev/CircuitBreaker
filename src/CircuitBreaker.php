<?php

declare(strict_types=1);

namespace Stuartwilsondev\CircuitBreaker;

use Stuartwilsondev\CircuitBreaker\Exceptions\HalfOpenCircuitException;
use Stuartwilsondev\CircuitBreaker\Exceptions\OpenCircuitException;
use Exception;

readonly class CircuitBreaker
{
    public function __construct(
        private CircuitBreakerStorageInterface $storage,
        private int                            $failureThreshold = 3
    ) {}

    /**
     * @throws OpenCircuitException
     * @throws HalfOpenCircuitException
     */
    public function call(string $serviceKey, callable $apiCall): mixed
    {

        match ($this->storage->getState($serviceKey)) {
            CircuitBreakerState::OPEN => throw new OpenCircuitException(sprintf('Circuit is open for "%s"', $serviceKey)),
            CircuitBreakerState::HALF_OPEN => throw new HalfOpenCircuitException(sprintf('Circuit is half-open for "%s"', $serviceKey)),
            default => null
        };

        try {
            $result = $apiCall();
            $this->storage->resetFailures($serviceKey);
            return $result;
        } catch (Exception $exception) {
            $this->storage->incrementFailures($serviceKey);
            $failures = $this->storage->getFailures($serviceKey);

            if ($failures >= $this->failureThreshold) {
                $this->storage->saveState($serviceKey, CircuitBreakerState::OPEN);
            }

            throw new OpenCircuitException(sprintf('Circuit is open for "%s". Latest error: %s',$serviceKey, $exception->getMessage()));
        }
    }
}