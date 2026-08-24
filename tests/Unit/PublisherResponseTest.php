<?php

declare(strict_types=1);

namespace Proppit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Proppit\DTO\PublisherResponse;

final class PublisherResponseTest extends TestCase
{
    // ── fromArray basics ──────────────────────────────────────────────────────

    public function test_fromArray_reads_id_as_publisherId(): void
    {
        $r = PublisherResponse::fromArray(['id' => 'homlity_agency_42', 'name' => 'Demo']);

        self::assertSame('homlity_agency_42', $r->publisherId());
        self::assertSame('homlity_agency_42', $r->publisherId);
    }

    public function test_fromArray_falls_back_to_publisherId_key(): void
    {
        $r = PublisherResponse::fromArray(['publisherId' => 'pub-abc', 'name' => 'Demo']);

        self::assertSame('pub-abc', $r->publisherId());
    }

    public function test_fromArray_reads_externalId(): void
    {
        $r = PublisherResponse::fromArray([
            'id'         => 'homlity_agency_42',
            'externalId' => 'homlity_agency_42',
            'name'       => 'Demo',
        ]);

        self::assertSame('homlity_agency_42', $r->externalId);
    }

    public function test_fromArray_reads_requestId(): void
    {
        $r = PublisherResponse::fromArray([
            'id'        => 'pub-1',
            'name'      => 'Demo',
            'requestId' => 'req-xyz',
        ]);

        self::assertSame('req-xyz', $r->requestId);
    }

    public function test_fromArray_reads_message(): void
    {
        $r = PublisherResponse::fromArray([
            'id'      => 'pub-1',
            'name'    => 'Demo',
            'message' => 'Publisher created.',
        ]);

        self::assertSame('Publisher created.', $r->message);
    }

    // ── Default: pending_activation after create/update ───────────────────────

    public function test_fromArray_defaults_to_pending_activation_when_no_confirmation(): void
    {
        $r = PublisherResponse::fromArray(['id' => 'pub-1', 'name' => 'Demo']);

        self::assertSame(PublisherResponse::STATUS_PENDING_ACTIVATION, $r->activationStatus);
        self::assertFalse($r->canPublish());
        self::assertTrue($r->isPendingActivation());
    }

    public function test_fromArray_pending_activation_when_response_has_no_active_field(): void
    {
        $r = PublisherResponse::fromArray([
            'id'    => 'pub-1',
            'name'  => 'Demo',
            'email' => 'a@b.com',
            'phone' => '+57300',
        ]);

        self::assertFalse($r->canPublish());
        self::assertTrue($r->isPendingActivation());
    }

    // ── Active state when Proppit confirms ───────────────────────────────────

    public function test_fromArray_active_when_canPublish_true(): void
    {
        $r = PublisherResponse::fromArray([
            'id'         => 'pub-1',
            'name'       => 'Demo',
            'canPublish' => true,
        ]);

        self::assertSame(PublisherResponse::STATUS_ACTIVE, $r->activationStatus);
        self::assertTrue($r->canPublish());
        self::assertFalse($r->isPendingActivation());
    }

    public function test_fromArray_pending_activation_when_canPublish_false(): void
    {
        $r = PublisherResponse::fromArray([
            'id'         => 'pub-1',
            'name'       => 'Demo',
            'canPublish' => false,
        ]);

        self::assertFalse($r->canPublish());
        self::assertTrue($r->isPendingActivation());
    }

    public function test_fromArray_active_when_active_boolean_true(): void
    {
        $r = PublisherResponse::fromArray([
            'id'     => 'pub-1',
            'name'   => 'Demo',
            'active' => true,
        ]);

        self::assertTrue($r->canPublish());
    }

    public function test_fromArray_pending_when_active_boolean_false(): void
    {
        $r = PublisherResponse::fromArray([
            'id'     => 'pub-1',
            'name'   => 'Demo',
            'active' => false,
        ]);

        self::assertFalse($r->canPublish());
        self::assertTrue($r->isPendingActivation());
    }

    public function test_fromArray_active_when_state_is_active(): void
    {
        $r = PublisherResponse::fromArray([
            'id'    => 'pub-1',
            'name'  => 'Demo',
            'state' => 'active',
        ]);

        self::assertTrue($r->canPublish());
    }

    public function test_fromArray_active_when_state_is_enabled(): void
    {
        $r = PublisherResponse::fromArray([
            'id'    => 'pub-1',
            'name'  => 'Demo',
            'state' => 'enabled',
        ]);

        self::assertTrue($r->canPublish());
    }

    public function test_fromArray_pending_activation_when_state_is_pending(): void
    {
        $r = PublisherResponse::fromArray([
            'id'    => 'pub-1',
            'name'  => 'Demo',
            'state' => 'pending',
        ]);

        self::assertSame(PublisherResponse::STATUS_PENDING_ACTIVATION, $r->activationStatus);
        self::assertFalse($r->canPublish());
    }

    public function test_fromArray_rejected_when_state_is_rejected(): void
    {
        $r = PublisherResponse::fromArray([
            'id'    => 'pub-1',
            'name'  => 'Demo',
            'state' => 'rejected',
        ]);

        self::assertSame(PublisherResponse::STATUS_REJECTED, $r->activationStatus);
        self::assertFalse($r->canPublish());
    }

    public function test_fromArray_active_when_status_is_active_string(): void
    {
        $r = PublisherResponse::fromArray([
            'id'     => 'pub-1',
            'name'   => 'Demo',
            'status' => 'active',
        ]);

        self::assertTrue($r->canPublish());
    }

    // ── isPendingActivation covers synced too ─────────────────────────────────

    public function test_fromArray_isPendingActivation_true_when_state_is_synced(): void
    {
        $r = PublisherResponse::fromArray([
            'id'    => 'pub-1',
            'name'  => 'Demo',
            'state' => 'synced',
        ]);

        self::assertSame(PublisherResponse::STATUS_SYNCED, $r->activationStatus);
        self::assertTrue($r->isPendingActivation());
        self::assertFalse($r->canPublish());
    }

    // ── canPublish priority: canPublish field over active/state ──────────────

    public function test_canPublish_field_takes_priority_over_active_boolean(): void
    {
        $r = PublisherResponse::fromArray([
            'id'         => 'pub-1',
            'name'       => 'Demo',
            'canPublish' => false,
            'active'     => true,
        ]);

        self::assertFalse($r->canPublish(), 'canPublish field must take priority');
    }

    // ── raw response preserved ────────────────────────────────────────────────

    public function test_raw_response_is_accessible(): void
    {
        $data = ['id' => 'pub-1', 'name' => 'Demo', 'active' => false, 'state' => 'pending_activation'];
        $r    = PublisherResponse::fromArray($data);

        self::assertSame($data, $r->raw);
    }

    // ── constants are defined ─────────────────────────────────────────────────

    public function test_status_constants_are_defined(): void
    {
        self::assertSame('pending_sync',       PublisherResponse::STATUS_PENDING_SYNC);
        self::assertSame('synced',             PublisherResponse::STATUS_SYNCED);
        self::assertSame('pending_activation', PublisherResponse::STATUS_PENDING_ACTIVATION);
        self::assertSame('active',             PublisherResponse::STATUS_ACTIVE);
        self::assertSame('cannot_publish',     PublisherResponse::STATUS_CANNOT_PUBLISH);
        self::assertSame('rejected',           PublisherResponse::STATUS_REJECTED);
        self::assertSame('error',              PublisherResponse::STATUS_ERROR);
    }
}
