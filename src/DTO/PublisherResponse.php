<?php

declare(strict_types=1);

namespace Propit\DTO;

/**
 * Response received from Proppit after creating or updating a publisher.
 *
 * The publisherId() value must be persisted by the consuming application
 * (e.g. in inmobiliarias.proppit_publisher_id) and used when linking ads.
 * In Proppit's schema this is the `id` field — the same value sent as externalId.
 */
final class PublisherResponse
{
    public function __construct(
        public readonly string $publisherId,
        public readonly string $name,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly array $raw,
    ) {
    }

    public function publisherId(): string { return $this->publisherId; }

    public static function fromArray(array $data): self
    {
        return new self(
            publisherId: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            email: isset($data['email']) ? (string) $data['email'] : null,
            phone: isset($data['phone']) ? (string) $data['phone'] : null,
            raw: $data,
        );
    }
}
