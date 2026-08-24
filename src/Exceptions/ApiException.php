<?php

declare(strict_types=1);

namespace Proppit\Exceptions;

class ApiException extends ProppitException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly string $method,
        public readonly string $endpoint,
        public readonly ?string $proppitErrorCode = null,
        array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $context, $statusCode, $previous);
    }
}
