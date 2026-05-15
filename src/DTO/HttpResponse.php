<?php

declare(strict_types=1);

namespace Propit\DTO;

final class HttpResponse
{
    public function __construct(
        public readonly int $statusCode,
        public readonly array $headers,
        public readonly string $rawBody,
        public readonly array $json = [],
    ) {
    }

    public function successful(): bool { return $this->statusCode >= 200 && $this->statusCode < 300; }
    public function failed(): bool { return !$this->successful(); }
    public function clientError(): bool { return $this->statusCode >= 400 && $this->statusCode < 500; }
    public function serverError(): bool { return $this->statusCode >= 500; }
}
