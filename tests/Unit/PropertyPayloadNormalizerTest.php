<?php

declare(strict_types=1);

namespace Propit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Propit\Exceptions\ValidationException;
use Propit\Normalizers\PropertyPayloadNormalizer;

final class PropertyPayloadNormalizerTest extends TestCase
{
    public function test_valid_payload_is_normalized(): void
    {
        $n = new PropertyPayloadNormalizer();
        $payload = [
            'referenceId' => 'CO-1',
            'publisher' => ['externalId' => 'pub-1'],
            'property' => [
                'type' => 'apartment',
                'location' => ['coordinates' => ['lat' => 4.7, 'long' => -74.1]],
            ],
            'operations' => [['type' => 'sell', 'price' => ['value' => 100, 'currency' => 'COP']]],
            'title' => ['locale' => 'es-CO', 'text' => 'Titulo'],
            'description' => ['locale' => 'es-CO', 'text' => 'Desc'],
            'multimedia' => ['pictures' => [['url' => 'https://example.com/img.jpg']]],
        ];

        $out = $n->normalize($payload);
        self::assertSame('CO-1', $out['referenceId']);
    }

    public function test_invalid_payload_throws(): void
    {
        $this->expectException(ValidationException::class);
        (new PropertyPayloadNormalizer())->normalize(['referenceId' => 'X']);
    }
}
