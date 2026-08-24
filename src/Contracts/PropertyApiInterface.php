<?php

declare(strict_types=1);

namespace Proppit\Contracts;

use Proppit\DTO\PropertyPayload;
use Proppit\DTO\PropertyResponse;
use Proppit\Exceptions\ProppitException;

interface PropertyApiInterface
{
    /** @throws ProppitException */
    public function publish(PropertyPayload|array $payload): PropertyResponse;

    /** @throws ProppitException */
    public function update(string $referenceId, PropertyPayload|array $payload): PropertyResponse;

    /** @throws ProppitException */
    public function find(string $referenceId): PropertyResponse;

    /** @throws ProppitException */
    public function findByExternalId(string $externalId, string $referenceId): ?PropertyResponse;

    /** @throws ProppitException */
    public function delete(string $referenceId): PropertyResponse;
}
