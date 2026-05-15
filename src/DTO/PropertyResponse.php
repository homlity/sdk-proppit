<?php

declare(strict_types=1);

namespace Propit\DTO;

final class PropertyResponse
{
    public function __construct(
        public readonly string $referenceId,
        public readonly ?string $status,
        public readonly array $data,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            referenceId: (string) ($data['referenceId'] ?? ''),
            status: isset($data['status']) ? (string) $data['status'] : null,
            data: $data,
        );
    }
}
