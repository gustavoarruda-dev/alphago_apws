<?php

namespace App\Exceptions;

use RuntimeException;

class ApwsApiException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode = 502,
        private readonly mixed $details = null,
    ) {
        parent::__construct($message, $statusCode);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function details(): mixed
    {
        return $this->details;
    }
}
