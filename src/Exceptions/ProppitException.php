<?php

declare(strict_types=1);

namespace Proppit\Exceptions;

use RuntimeException;

class ProppitException extends RuntimeException
{
    public function __construct(string $message, protected array $context = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function context(): array
    {
        return $this->context;
    }
}
