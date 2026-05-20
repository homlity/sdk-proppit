<?php

declare(strict_types=1);

namespace Propit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Propit\Exceptions\ValidationException;
use Propit\Normalizers\PropertyPayloadNormalizer;

final class PropertyPayloadNormalizerTest extends TestCase
{
    private PropertyPayloadNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new PropertyPayloadNormalizer();
    }

    // ── Minimal valid payload ────────────────────────────────────────────────

    public function test_valid_minimal_payload_passes(): void
    {
        $out = $this->normalizer->normalize($this->minimalPayload());

        self::assertSame('CO-1', $out['referenceId']);
        self::assertSame('pub-1', $out['publisher']['externalId']);
    }

    public function test_valid_payload_with_all_optional_fields_passes(): void
    {
        $payload = array_merge($this->minimalPayload(), [
            'contact'    => ['name' => 'Agent', 'email' => 'a@b.com', 'phone' => '+571234'],
            'multimedia' => ['pictures' => [['url' => 'https://img.example.com/1.jpg']]],
            'floorArea'  => ['value' => 80.5, 'unit' => 'sqm'],
            'totalArea'  => ['value' => 200.0, 'unit' => 'sqm'],
            'usableArea' => ['value' => 75.0, 'unit' => 'sqm'],
            'bedrooms'   => 3,
            'bathrooms'  => 2,
            'parkingSpaces' => 1,
            'stratum'    => 4,
            'condition'  => 'excellent',
            'furnished'  => 'unfurnished',
            'amenities'  => ['gym', 'swimming pool'],
        ]);

        $out = $this->normalizer->normalize($payload);
        self::assertSame('excellent', $out['condition']);
        self::assertSame(4, $out['stratum']);
    }

    // ── Required fields ──────────────────────────────────────────────────────

    /** @dataProvider missingRequiredFieldProvider */
    public function test_missing_required_field_throws(string $field): void
    {
        $payload = $this->minimalPayload();
        unset($payload[$field]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/\b' . preg_quote($field, '/') . '\b/i');

        $this->normalizer->normalize($payload);
    }

    public static function missingRequiredFieldProvider(): array
    {
        return [
            'referenceId' => ['referenceId'],
            'publisher'   => ['publisher'],
            'property'    => ['property'],
            'operations'  => ['operations'],
            'title'       => ['title'],
            'description' => ['description'],
        ];
    }

    public function test_empty_referenceId_throws(): void
    {
        $payload = array_merge($this->minimalPayload(), ['referenceId' => '   ']);
        $this->expectException(ValidationException::class);
        $this->normalizer->normalize($payload);
    }

    // ── publisher ────────────────────────────────────────────────────────────

    public function test_missing_publisher_externalId_throws(): void
    {
        $payload = $this->minimalPayload();
        $payload['publisher'] = [];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/publisher\.externalId/i');
        $this->normalizer->normalize($payload);
    }

    public function test_empty_publisher_externalId_throws(): void
    {
        $payload = $this->minimalPayload();
        $payload['publisher']['externalId'] = '   ';

        $this->expectException(ValidationException::class);
        $this->normalizer->normalize($payload);
    }

    // ── property ─────────────────────────────────────────────────────────────

    public function test_missing_property_type_throws(): void
    {
        $payload = $this->minimalPayload();
        unset($payload['property']['type']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/property\.type/i');
        $this->normalizer->normalize($payload);
    }

    public function test_missing_coordinates_throws(): void
    {
        $payload = $this->minimalPayload();
        unset($payload['property']['location']['coordinates']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/coordinates/i');
        $this->normalizer->normalize($payload);
    }

    public function test_non_numeric_lat_throws(): void
    {
        $payload = $this->minimalPayload();
        $payload['property']['location']['coordinates']['lat'] = 'north';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/lat/i');
        $this->normalizer->normalize($payload);
    }

    public function test_non_numeric_long_throws(): void
    {
        $payload = $this->minimalPayload();
        $payload['property']['location']['coordinates']['long'] = 'west';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/long/i');
        $this->normalizer->normalize($payload);
    }

    public function test_invalid_visibility_throws(): void
    {
        $payload = $this->minimalPayload();
        $payload['property']['location']['visibility'] = 'hidden';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/visibility/i');
        $this->normalizer->normalize($payload);
    }

    public function test_valid_visibility_accurate_passes(): void
    {
        $payload = $this->minimalPayload();
        $payload['property']['location']['visibility'] = 'accurate';

        $out = $this->normalizer->normalize($payload);
        self::assertSame('accurate', $out['property']['location']['visibility']);
    }

    // ── operations ───────────────────────────────────────────────────────────

    public function test_empty_operations_throws(): void
    {
        $payload = array_merge($this->minimalPayload(), ['operations' => []]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/operations/i');
        $this->normalizer->normalize($payload);
    }

    public function test_invalid_operation_type_throws(): void
    {
        $payload = $this->minimalPayload();
        $payload['operations'][0]['type'] = 'trade';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/operations\[0\]\.type/i');
        $this->normalizer->normalize($payload);
    }

    public function test_rent_operation_type_passes(): void
    {
        $payload = $this->minimalPayload();
        $payload['operations'][0]['type'] = 'rent';

        $out = $this->normalizer->normalize($payload);
        self::assertSame('rent', $out['operations'][0]['type']);
    }

    public function test_invalid_currency_throws(): void
    {
        $payload = $this->minimalPayload();
        $payload['operations'][0]['price']['currency'] = 'BTC';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/currency/i');
        $this->normalizer->normalize($payload);
    }

    public function test_non_numeric_price_value_throws(): void
    {
        $payload = $this->minimalPayload();
        $payload['operations'][0]['price']['value'] = 'mucho';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/price\.value/i');
        $this->normalizer->normalize($payload);
    }

    // ── title / description ──────────────────────────────────────────────────

    public function test_invalid_title_locale_throws(): void
    {
        $payload = $this->minimalPayload();
        $payload['title']['locale'] = 'es-ZZ';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/title\.locale/i');
        $this->normalizer->normalize($payload);
    }

    public function test_empty_title_text_throws(): void
    {
        $payload = $this->minimalPayload();
        $payload['title']['text'] = '';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/title\.text/i');
        $this->normalizer->normalize($payload);
    }

    public function test_invalid_description_locale_throws(): void
    {
        $payload = $this->minimalPayload();
        $payload['description']['locale'] = 'fr-FR';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/description\.locale/i');
        $this->normalizer->normalize($payload);
    }

    public function test_empty_description_text_throws(): void
    {
        $payload = $this->minimalPayload();
        $payload['description']['text'] = '   ';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/description\.text/i');
        $this->normalizer->normalize($payload);
    }

    /** @dataProvider validLocaleProvider */
    public function test_all_valid_locales_pass(string $locale): void
    {
        $payload = $this->minimalPayload();
        $payload['title']['locale']       = $locale;
        $payload['description']['locale'] = $locale;

        $out = $this->normalizer->normalize($payload);
        self::assertSame($locale, $out['title']['locale']);
    }

    public static function validLocaleProvider(): array
    {
        return array_map(
            fn (string $l) => [$l],
            ['es-AR', 'es-CL', 'es-CO', 'es-MX', 'es-PE', 'en-PH', 'id-ID', 'th-TH', 'vi-VN', 'es-ES', 'es-PA', 'es-EC']
        );
    }

    // ── multimedia ───────────────────────────────────────────────────────────

    public function test_invalid_picture_url_throws(): void
    {
        $payload = array_merge($this->minimalPayload(), [
            'multimedia' => ['pictures' => [['url' => 'not-a-url']]],
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/pictures\[0\]\.url/i');
        $this->normalizer->normalize($payload);
    }

    public function test_picture_without_url_key_throws(): void
    {
        $payload = array_merge($this->minimalPayload(), [
            'multimedia' => ['pictures' => [['src' => 'https://example.com/img.jpg']]],
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/url/i');
        $this->normalizer->normalize($payload);
    }

    // ── optional enums ───────────────────────────────────────────────────────

    public function test_invalid_condition_throws(): void
    {
        $payload = array_merge($this->minimalPayload(), ['condition' => 'mint']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/condition/i');
        $this->normalizer->normalize($payload);
    }

    public function test_invalid_furnished_throws(): void
    {
        $payload = array_merge($this->minimalPayload(), ['furnished' => 'yes']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/furnished/i');
        $this->normalizer->normalize($payload);
    }

    public function test_stratum_below_1_throws(): void
    {
        $payload = array_merge($this->minimalPayload(), ['stratum' => 0]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/stratum/i');
        $this->normalizer->normalize($payload);
    }

    public function test_stratum_above_7_throws(): void
    {
        $payload = array_merge($this->minimalPayload(), ['stratum' => 8]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/stratum/i');
        $this->normalizer->normalize($payload);
    }

    // ── areas ────────────────────────────────────────────────────────────────

    public function test_invalid_area_unit_throws(): void
    {
        $payload = array_merge($this->minimalPayload(), [
            'floorArea' => ['value' => 80, 'unit' => 'feet'],
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/floorArea\.unit/i');
        $this->normalizer->normalize($payload);
    }

    public function test_non_numeric_area_value_throws(): void
    {
        $payload = array_merge($this->minimalPayload(), [
            'floorArea' => ['value' => 'big', 'unit' => 'sqm'],
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/floorArea\.value/i');
        $this->normalizer->normalize($payload);
    }

    // ── null/empty cleanup ───────────────────────────────────────────────────

    public function test_null_values_are_stripped(): void
    {
        $payload                                      = $this->minimalPayload();
        $payload['property']['location']['postcode']  = null;
        $payload['property']['location']['address']   = null;

        $out = $this->normalizer->normalize($payload);
        self::assertArrayNotHasKey('postcode', $out['property']['location']);
        self::assertArrayNotHasKey('address', $out['property']['location']);
    }

    public function test_empty_string_values_are_stripped(): void
    {
        $payload                              = $this->minimalPayload();
        $payload['property']['location']['address'] = '';

        $out = $this->normalizer->normalize($payload);
        self::assertArrayNotHasKey('address', $out['property']['location']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function minimalPayload(): array
    {
        return [
            'referenceId' => 'CO-1',
            'publisher'   => ['externalId' => 'pub-1'],
            'property'    => [
                'type'     => 'apartment',
                'location' => [
                    'coordinates' => ['lat' => 4.711, 'long' => -74.0721],
                ],
            ],
            'operations'  => [
                ['type' => 'sell', 'price' => ['value' => 450000000, 'currency' => 'COP']],
            ],
            'title'       => ['locale' => 'es-CO', 'text' => 'Apartamento en venta'],
            'description' => ['locale' => 'es-CO', 'text' => 'Amplio apartamento'],
        ];
    }
}
