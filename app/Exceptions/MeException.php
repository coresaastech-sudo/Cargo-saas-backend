<?php

namespace App\Exceptions;

use RuntimeException;

class MeException extends RuntimeException
{
    public function __construct(
        private readonly string $responseCode,
        private readonly array $data = [],
        private readonly int $status = 400,
        ?string $message = null,
    ) {
        parent::__construct($message ?: $responseCode);
    }

    public function responseCode(): string
    {
        return $this->responseCode;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function status(): int
    {
        return $this->status;
    }
}
