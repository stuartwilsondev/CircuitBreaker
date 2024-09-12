<?php

declare(strict_types=1);

namespace Stuartwilsondev\CircuitBreaker\ValueObject;

interface ResultInterface
{
    public function isSuccess(): bool;

    public function getResult(): mixed;

    public static function success(mixed $result): ResultInterface;

    public static function failure(mixed $result): ResultInterface;
}