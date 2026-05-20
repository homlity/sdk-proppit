<?php

declare(strict_types=1);

namespace Propit\DTO;

/**
 * Represents a publisher (agency/inmobiliaria) to be registered or updated in Proppit.
 *
 * The externalId must be generated and owned by Homlity. Recommended format:
 *   homlity_agency_{inmobiliaria_id}
 *   homlity_agency_{inmobiliaria_uuid}
 *
 * This value becomes the `id` field in the Proppit API and the `publisher.externalId`
 * field when creating or updating ads linked to this publisher.
 */
final class PublisherPayload
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $phone = null,
    ) {
    }
}
