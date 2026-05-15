<?php

declare(strict_types=1);

namespace Propit\Contracts;

use Propit\DTO\PropertyPayload;
use Propit\DTO\PropertyResponse;
use Propit\Exceptions\PropitException;

interface PropertyApiInterface
{
    /** @throws PropitException */
    public function publish(PropertyPayload|array $payload): PropertyResponse;

    /** @throws PropitException */
    public function update(string $referenceId, PropertyPayload|array $payload): PropertyResponse;

    /** @throws PropitException */
    public function find(string $referenceId): PropertyResponse;

    /** @throws PropitException */
    public function findByExternalId(string $externalId, string $referenceId): ?PropertyResponse;

    /** @throws PropitException */
    public function delete(string $referenceId): PropertyResponse;
}
