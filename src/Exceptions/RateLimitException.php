<?php

declare(strict_types=1);

namespace Propit\Exceptions;

final class RateLimitException extends ApiException
{
    public function __construct(
        string $message,
        int $statusCode,
        string $method,
        string $endpoint,
        public readonly ?int $retryAfter,
        array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $method, $endpoint, null, $context, $previous);
    }
}
