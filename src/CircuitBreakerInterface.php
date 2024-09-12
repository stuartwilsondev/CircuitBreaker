<?php

declare(strict_types=1);

namespace Stuartwilsondev\CircuitBreaker;

use Stuartwilsondev\CircuitBreaker\ValueObject\OperationInterface;
use Stuartwilsondev\CircuitBreaker\ValueObject\ResultInterface;

interface CircuitBreakerInterface
{
    public function call(string $serviceKey, OperationInterface $operation): ResultInterface;
}