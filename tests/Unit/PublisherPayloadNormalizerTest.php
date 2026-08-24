<?php

declare(strict_types=1);

namespace Proppit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Proppit\DTO\PublisherPayload;
use Proppit\Exceptions\ValidationException;
use Proppit\Normalizers\PublisherPayloadNormalizer;

final class PublisherPayloadNormalizerTest extends TestCase
{
    private PublisherPayloadNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new PublisherPayloadNormalizer();
    }

    // ── Valid payloads ────────────────────────────────────────────────────────

    public function test_valid_payload_maps_externalId_to_id_field(): void
    {
        $body = $this->normalizer->normalize(new PublisherPayload(
            externalId: 'homlity_agency_42',
            name: 'Inmobiliaria Demo',
            email: 'contacto@demo.com',
        ));

        self::assertSame('homlity_agency_42', $body['id']);
        self::assertSame('Inmobiliaria Demo', $body['name']);
        self::assertSame('contacto@demo.com', $body['email']);
        self::assertArrayNotHasKey('externalId', $body);
    }

    public function test_phone_is_included_when_provided(): void
    {
        $body = $this->normalizer->normalize(new PublisherPayload(
            externalId: 'homlity_agency_1',
            name: 'Agency',
            email: 'a@b.com',
            phone: '+573001112233',
        ));

        self::assertSame('+573001112233', $body['phone']);
    }

    public function test_phone_is_omitted_when_null(): void
    {
        $body = $this->normalizer->normalize(new PublisherPayload(
            externalId: 'homlity_agency_1',
            name: 'Agency',
            email: 'a@b.com',
            phone: null,
        ));

        self::assertArrayNotHasKey('phone', $body);
    }

    public function test_email_as_externalId_is_valid(): void
    {
        $body = $this->normalizer->normalize(new PublisherPayload(
            externalId: 'agency@example.com',
            name: 'Agency',
            email: 'contact@example.com',
        ));

        self::assertSame('agency@example.com', $body['id']);
    }

    // ── externalId validation ─────────────────────────────────────────────────

    public function test_empty_externalId_throws_ValidationException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/externalId/');

        $this->normalizer->normalize(new PublisherPayload(
            externalId: '   ',
            name: 'Agency',
            email: 'a@b.com',
        ));
    }

    public function test_externalId_exceeding_255_chars_throws(): void
    {
        $this->expectException(ValidationException::class);

        $this->normalizer->normalize(new PublisherPayload(
            externalId: str_repeat('a', 256),
            name: 'Agency',
            email: 'a@b.com',
        ));
    }

    public function test_externalId_with_invalid_characters_throws(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/invalid characters/i');

        $this->normalizer->normalize(new PublisherPayload(
            externalId: 'agency with spaces',
            name: 'Agency',
            email: 'a@b.com',
        ));
    }

    public function test_externalId_with_hyphens_and_underscores_passes(): void
    {
        $body = $this->normalizer->normalize(new PublisherPayload(
            externalId: 'homlity_agency-42',
            name: 'Agency',
            email: 'a@b.com',
        ));

        self::assertSame('homlity_agency-42', $body['id']);
    }

    // ── name / email validation ───────────────────────────────────────────────

    public function test_empty_name_throws_ValidationException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/name/');

        $this->normalizer->normalize(new PublisherPayload(
            externalId: 'homlity_agency_1',
            name: '   ',
            email: 'a@b.com',
        ));
    }

    public function test_empty_email_throws_ValidationException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/email/');

        $this->normalizer->normalize(new PublisherPayload(
            externalId: 'homlity_agency_1',
            name: 'Agency',
            email: '',
        ));
    }
}
