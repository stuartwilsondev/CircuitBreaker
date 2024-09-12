<?php

declare(strict_types=1);

namespace Stuartwilsondev\CircuitBreaker\ValueObject;

class Result implements ResultInterface
{

    private bool $success;

    private mixed $result;

    private function __construct(bool $success, mixed $result)
    {
        $this->success = $success;
        $this->result = $result;
    }

    public static function success(mixed $result): Result
    {
        return new Result(true, $result);
    }

    public static function failure(mixed $result): Result
    {
        return new Result(false, $result);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getResult(): mixed
    {
        return $this->result;
    }
}