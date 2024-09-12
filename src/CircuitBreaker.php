<?php

declare(strict_types=1);

namespace Stuartwilsondev\CircuitBreaker;

use DateTimeImmutable;
use Stuartwilsondev\CircuitBreaker\Exceptions\HalfOpenCircuitException;
use Stuartwilsondev\CircuitBreaker\Exceptions\OpenCircuitException;
use Stuartwilsondev\CircuitBreaker\Exceptions\OperationExecuteException;
use Stuartwilsondev\CircuitBreaker\ValueObject\OperationInterface;
use Stuartwilsondev\CircuitBreaker\ValueObject\ResultInterface;

readonly class CircuitBreaker implements CircuitBreakerInterface
{
    private const MESSAGE_CIRCUIT_IS_OPEN_FOR_S = 'Circuit is open for "%s"';

    private const MESSAGE_HALF_OPEN_STATE_FAILED_FOR_S_LATEST_ERROR_S = 'Half-open state failed for "%s". Latest error: %s';

    private const  MESSAGE_CIRCUIT_IS_OPEN_FOR_S_LATEST_ERROR_S = 'Circuit is open for "%s". Latest error: %s';

    public function __construct(
        private CircuitBreakerStorageInterface $storage,
        private int                            $failureThreshold = 3,
        private int                            $timeoutInSeconds = 5,
    ) {}

    /**
     * @throws HalfOpenCircuitException
     * @throws OperationExecuteException
     * @throws OpenCircuitException
     */
    public function call(string $serviceKey, OperationInterface $operation): ResultInterface
    {
        if($this->storage->getState($serviceKey) === CircuitBreakerState::OPEN) {
            $this->handleOpenToHalfOpen($serviceKey);
        }

        return match ($this->storage->getState($serviceKey)) {
            CircuitBreakerState::OPEN => throw new OpenCircuitException(sprintf(self::MESSAGE_CIRCUIT_IS_OPEN_FOR_S, $serviceKey)),
            default => $this->handleOperation($serviceKey, $operation)
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


    private function handleOperation(string $serviceKey, OperationInterface $operation): ResultInterface
    {
        try {
            $result = $operation->execute();
            if ($this->storage->getState($serviceKey) === CircuitBreakerState::HALF_OPEN) {
                $this->storage->saveState($serviceKey, CircuitBreakerState::CLOSED);
            }

            $this->storage->resetFailures($serviceKey);
            return $result;
        } catch (OperationExecuteException $operationExecuteException) {
            if ($this->storage->getState($serviceKey) === CircuitBreakerState::HALF_OPEN) {
                $this->storage->saveState($serviceKey, CircuitBreakerState::OPEN);
                $this->storage->setLastOpenDateTime($serviceKey, new DateTimeImmutable());
                throw new HalfOpenCircuitException(sprintf(self::MESSAGE_HALF_OPEN_STATE_FAILED_FOR_S_LATEST_ERROR_S, $serviceKey, $operationExecuteException->getMessage()));
            }

            $this->storage->incrementFailures($serviceKey);
            $failures = $this->storage->getFailures($serviceKey);
            if ($failures >= $this->failureThreshold) {
                $this->storage->saveState($serviceKey, CircuitBreakerState::OPEN);
                $this->storage->setLastOpenDateTime($serviceKey, new DateTimeImmutable());
                throw new OpenCircuitException(sprintf(self::MESSAGE_CIRCUIT_IS_OPEN_FOR_S_LATEST_ERROR_S, $serviceKey, $operationExecuteException->getMessage()));
            }

            throw $operationExecuteException;
        }
    }
}