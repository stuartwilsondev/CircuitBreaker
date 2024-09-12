<?php

declare(strict_types=1);

namespace Stuartwilsondev\CircuitBreaker\ValueObject;

use Stuartwilsondev\CircuitBreaker\Exceptions\OperationExecuteException;

interface OperationInterface
{
    /**
     * @throws OperationExecuteException on failure
     */
    public function execute(): ResultInterface;

}
