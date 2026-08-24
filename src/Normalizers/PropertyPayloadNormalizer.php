<?php

declare(strict_types=1);

namespace Proppit\Normalizers;

use Proppit\Contracts\PropertyPayloadMapperInterface;
use Proppit\DTO\PropertyPayload;
use Proppit\Exceptions\ValidationException;

/**
 * Validates and normalizes an Ad payload before it is sent to the Proppit API.
 *
 * Field rules are derived exclusively from the official OpenAPI spec at:
 *   https://real-time.proppit.com/api/v2/docs/openapi.yaml (OpenAPI 3.1.0)
 */
final class PropertyPayloadNormalizer implements PropertyPayloadMapperInterface
{
    // Locale enum from the spec (LocalisedText.locale)
    private const VALID_LOCALES = [
        'es-AR', 'es-CL', 'es-CO', 'es-MX', 'es-PE',
        'en-PH', 'id-ID', 'th-TH', 'vi-VN',
        'es-ES', 'es-PA', 'es-EC',
    ];

    // Currency enum from the spec
    private const VALID_CURRENCIES = [
        'AED', 'ARS', 'CLF', 'CLP', 'COP',
        'EUR', 'MXN', 'PAB', 'PEN', 'PHP',
        'THB', 'USD', 'VND', 'IDR',
    ];

    // Operation type enum
    private const VALID_OPERATION_TYPES = ['rent', 'sell'];

    // Condition enum from the spec (optional field)
    private const VALID_CONDITIONS = [
        'excellent', 'good', 'normal', 'to renew', 'second hand',
        'ruin', 'semi-renovated', 'semi-new', 'in construction', 'new', 'renovated',
    ];

    // Furnished enum from the spec (optional field)
    private const VALID_FURNISHED = ['fully', 'partly', 'unfurnished'];

    // Visibility enum from the spec (optional within location)
    private const VALID_VISIBILITY = ['accurate', 'approximate'];

    // Area unit — only sqm is documented in examples; spec says string
    private const VALID_AREA_UNITS = ['sqm'];

    public function normalize(PropertyPayload|array $payload): array
    {
        $data = $payload instanceof PropertyPayload ? $payload->toArray() : $payload;

        $this->validateRequired($data);
        $this->validatePublisher($data);
        $this->validateProperty($data);
        $this->validateOperations($data);
        $this->validateLocalisedText('title', $data);
        $this->validateLocalisedText('description', $data);

        if (isset($data['multimedia'])) {
            $this->validateMultimedia($data['multimedia']);
        }

        if (isset($data['condition'])) {
            $this->validateEnum('condition', $data['condition'], self::VALID_CONDITIONS);
        }

        if (isset($data['furnished'])) {
            $this->validateEnum('furnished', $data['furnished'], self::VALID_FURNISHED);
        }

        foreach (['totalArea', 'floorArea', 'usableArea'] as $areaField) {
            if (isset($data[$areaField])) {
                $this->validateArea($areaField, $data[$areaField]);
            }
        }

        if (isset($data['stratum'])) {
            $stratum = (int) $data['stratum'];
            if ($stratum < 1 || $stratum > 7) {
                throw new ValidationException('stratum must be between 1 and 7 (only valid for CO).');
            }
        }

        return $this->cleanNulls($data);
    }

    // ── Validators ───────────────────────────────────────────────────────────

    private function validateRequired(array $data): void
    {
        $required = ['referenceId', 'publisher', 'property', 'operations', 'title', 'description'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                throw new ValidationException("Missing required field: {$field}.");
            }
        }

        if (trim((string) ($data['referenceId'] ?? '')) === '') {
            throw new ValidationException('referenceId must not be empty.');
        }
    }

    private function validatePublisher(array $data): void
    {
        if (!is_array($data['publisher'] ?? null)) {
            throw new ValidationException('publisher must be an object.');
        }

        $externalId = trim((string) ($data['publisher']['externalId'] ?? ''));
        if ($externalId === '') {
            throw new ValidationException('publisher.externalId is required and must not be empty.');
        }
    }

    private function validateProperty(array $data): void
    {
        $property = $data['property'] ?? null;
        if (!is_array($property)) {
            throw new ValidationException('property must be an object.');
        }

        if (empty($property['type'])) {
            throw new ValidationException('property.type is required.');
        }

        $location = $property['location'] ?? null;
        if (!is_array($location)) {
            throw new ValidationException('property.location is required.');
        }

        $coords = $location['coordinates'] ?? null;
        if (!is_array($coords)) {
            throw new ValidationException('property.location.coordinates is required.');
        }

        if (!isset($coords['lat']) || !is_numeric($coords['lat'])) {
            throw new ValidationException('property.location.coordinates.lat must be a number.');
        }

        if (!isset($coords['long']) || !is_numeric($coords['long'])) {
            throw new ValidationException('property.location.coordinates.long must be a number.');
        }

        if (isset($location['visibility'])) {
            $this->validateEnum('property.location.visibility', $location['visibility'], self::VALID_VISIBILITY);
        }
    }

    private function validateOperations(array $data): void
    {
        $operations = $data['operations'] ?? [];
        if (!is_array($operations) || $operations === []) {
            throw new ValidationException('operations must be a non-empty array.');
        }

        foreach ($operations as $idx => $op) {
            if (!is_array($op)) {
                throw new ValidationException("operations[{$idx}] must be an object.");
            }

            if (!in_array($op['type'] ?? null, self::VALID_OPERATION_TYPES, true)) {
                throw new ValidationException(
                    "operations[{$idx}].type must be one of: " . implode(', ', self::VALID_OPERATION_TYPES) . '.'
                );
            }

            $price = $op['price'] ?? null;
            if (!is_array($price)) {
                throw new ValidationException("operations[{$idx}].price must be an object.");
            }

            if (!isset($price['value']) || !is_numeric($price['value'])) {
                throw new ValidationException("operations[{$idx}].price.value must be a number.");
            }

            $currency = strtoupper(trim((string) ($price['currency'] ?? '')));
            if (!in_array($currency, self::VALID_CURRENCIES, true)) {
                throw new ValidationException(
                    "operations[{$idx}].price.currency must be one of: " . implode(', ', self::VALID_CURRENCIES) . '.'
                );
            }
        }
    }

    private function validateLocalisedText(string $field, array $data): void
    {
        $text = $data[$field] ?? null;
        if (!is_array($text)) {
            throw new ValidationException("{$field} must be an object with locale and text.");
        }

        $locale = trim((string) ($text['locale'] ?? ''));
        if ($locale === '') {
            throw new ValidationException("{$field}.locale is required.");
        }

        if (!in_array($locale, self::VALID_LOCALES, true)) {
            throw new ValidationException(
                "{$field}.locale must be one of: " . implode(', ', self::VALID_LOCALES) . ". Got: '{$locale}'."
            );
        }

        if (trim((string) ($text['text'] ?? '')) === '') {
            throw new ValidationException("{$field}.text must not be empty.");
        }
    }

    private function validateMultimedia(mixed $multimedia): void
    {
        if (!is_array($multimedia)) {
            throw new ValidationException('multimedia must be an object.');
        }

        if (!isset($multimedia['pictures'])) {
            return;
        }

        if (!is_array($multimedia['pictures'])) {
            throw new ValidationException('multimedia.pictures must be an array.');
        }

        foreach ($multimedia['pictures'] as $i => $picture) {
            if (!is_array($picture) || !isset($picture['url'])) {
                throw new ValidationException("multimedia.pictures[{$i}] must have a url field.");
            }
            $url = (string) $picture['url'];
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                throw new ValidationException("multimedia.pictures[{$i}].url must be a valid URL. Got: '{$url}'.");
            }
        }
    }

    private function validateArea(string $field, mixed $area): void
    {
        if (!is_array($area)) {
            throw new ValidationException("{$field} must be an object with value and unit.");
        }

        if (!isset($area['value']) || !is_numeric($area['value'])) {
            throw new ValidationException("{$field}.value must be a number.");
        }

        $unit = strtolower(trim((string) ($area['unit'] ?? '')));
        if (!in_array($unit, self::VALID_AREA_UNITS, true)) {
            throw new ValidationException(
                "{$field}.unit must be one of: " . implode(', ', self::VALID_AREA_UNITS) . ". Got: '{$unit}'."
            );
        }
    }

    private function validateEnum(string $field, mixed $value, array $allowed): void
    {
        if (!in_array($value, $allowed, true)) {
            throw new ValidationException(
                "{$field} must be one of: " . implode(', ', $allowed) . ". Got: '{$value}'."
            );
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function cleanNulls(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = $this->cleanNulls($v);
                continue;
            }
            if ($data[$k] === null || $data[$k] === '') {
                unset($data[$k]);
            }
        }

        return $data;
    }
}
