<?php

declare(strict_types=1);

namespace Propit\Contracts;

use Propit\DTO\PropertyPayload;
use Propit\Exceptions\ValidationException;

interface PropertyPayloadMapperInterface
{
    /** @throws ValidationException */
    public function normalize(PropertyPayload|array $payload): array;
}
