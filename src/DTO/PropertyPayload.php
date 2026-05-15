<?php

declare(strict_types=1);

namespace Propit\DTO;

final class PropertyPayload
{
    public function __construct(private readonly array $data)
    {
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
