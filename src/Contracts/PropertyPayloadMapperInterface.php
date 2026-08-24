<?php

declare(strict_types=1);

namespace Proppit\Contracts;

use Proppit\DTO\PropertyPayload;
use Proppit\Exceptions\ValidationException;

interface PropertyPayloadMapperInterface
{
    /** @throws ValidationException */
    public function normalize(PropertyPayload|array $payload): array;
}
