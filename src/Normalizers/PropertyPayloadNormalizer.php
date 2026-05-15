<?php

declare(strict_types=1);

namespace Propit\Normalizers;

use Propit\Contracts\PropertyPayloadMapperInterface;
use Propit\DTO\PropertyPayload;
use Propit\Exceptions\ValidationException;

final class PropertyPayloadNormalizer implements PropertyPayloadMapperInterface
{
    public function normalize(PropertyPayload|array $payload): array
    {
        $data = $payload instanceof PropertyPayload ? $payload->toArray() : $payload;

        $required = ['referenceId', 'publisher', 'property', 'operations', 'title', 'description'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                throw new ValidationException("Missing required field: {$field}");
            }
        }

        if (!isset($data['publisher']['externalId']) || trim((string) $data['publisher']['externalId']) === '') {
            throw new ValidationException('publisher.externalId is required.');
        }

        if (!isset($data['property']['type']) || !isset($data['property']['location']['coordinates']['lat']) || !isset($data['property']['location']['coordinates']['long'])) {
            throw new ValidationException('property.type and property.location.coordinates.lat/long are required.');
        }

        foreach ((array) ($data['operations'] ?? []) as $idx => $op) {
            if (!in_array($op['type'] ?? null, ['rent', 'sell'], true)) {
                throw new ValidationException("operations[{$idx}].type must be rent|sell");
            }
            if (!isset($op['price']['value'], $op['price']['currency'])) {
                throw new ValidationException("operations[{$idx}].price.value and currency are required");
            }
        }

        if (isset($data['multimedia']['pictures'])) {
            foreach ((array) $data['multimedia']['pictures'] as $i => $picture) {
                $url = (string) ($picture['url'] ?? '');
                if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
                    throw new ValidationException("multimedia.pictures[{$i}].url must be a valid URL");
                }
            }
        }

        $data = $this->cleanNulls($data);

        return $data;
    }

    private function cleanNulls(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = $this->cleanNulls($v);
            }
            if ($data[$k] === null || $data[$k] === '') {
                unset($data[$k]);
            }
        }

        return $data;
    }
}
