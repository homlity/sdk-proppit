<?php

declare(strict_types=1);

namespace Proppit\DTO;

/**
 * Response received from Proppit after creating, updating, or querying a publisher.
 *
 * IMPORTANT — publisher created ≠ publisher active:
 *   After sync/create, Proppit receives the publisher but does NOT automatically
 *   enable it. Proppit must manually activate/connect the publisher before ads
 *   can be sent. Until then, activationStatus is 'pending_activation' and
 *   canPublish() returns false.
 *
 * Activation status values:
 *   pending_sync        — publisher not yet sent to Proppit
 *   synced              — publisher sent, awaiting Proppit to confirm activation
 *   pending_activation  — default after create/update: Proppit received it but
 *                         has not confirmed the publisher can publish
 *   active              — Proppit confirmed the publisher can publish
 *   cannot_publish      — Proppit responded with 403 (publisher not enabled)
 *   rejected            — Proppit explicitly rejected the publisher
 *   error               — unexpected state from API response
 *
 * Fields from Proppit response:
 *   - publisherId  : value of 'id' in Proppit's response (same as externalId sent)
 *   - externalId   : value of 'externalId' if returned separately by Proppit
 *   - requestId    : Proppit's x-request-id or requestId field
 *   - message      : any message field returned by Proppit
 *   - raw          : full raw API response for inspection
 *
 * Persist by consuming application (Homlity):
 *   proppit_publisher_id, proppit_external_id, proppit_publisher_status,
 *   proppit_can_publish, proppit_last_request_id, proppit_last_synced_at
 *
 * See docs/publisher-integration.md for the complete activation flow.
 */
final class PublisherResponse
{
    public const STATUS_PENDING_SYNC       = 'pending_sync';
    public const STATUS_SYNCED             = 'synced';
    public const STATUS_PENDING_ACTIVATION = 'pending_activation';
    public const STATUS_ACTIVE             = 'active';
    public const STATUS_CANNOT_PUBLISH     = 'cannot_publish';
    public const STATUS_REJECTED           = 'rejected';
    public const STATUS_ERROR              = 'error';

    public function __construct(
        public readonly string  $publisherId,
        public readonly string  $name,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly array   $raw,
        public readonly string  $activationStatus = self::STATUS_PENDING_ACTIVATION,
        public readonly ?string $externalId       = null,
        public readonly ?string $requestId        = null,
        public readonly ?string $message          = null,
    ) {
    }

    public function publisherId(): string
    {
        return $this->publisherId;
    }

    /**
     * Returns true only if Proppit explicitly confirmed the publisher can publish.
     *
     * After create/update this will return false until Proppit activates the publisher.
     * Do NOT assume true after a successful sync response.
     */
    public function canPublish(): bool
    {
        return $this->activationStatus === self::STATUS_ACTIVE;
    }

    /**
     * Returns true when the publisher was sent to Proppit but Proppit has not yet
     * confirmed activation. This is the default state after create/update.
     */
    public function isPendingActivation(): bool
    {
        return $this->activationStatus === self::STATUS_PENDING_ACTIVATION
            || $this->activationStatus === self::STATUS_SYNCED;
    }

    /**
     * Builds a PublisherResponse from a Proppit API response array.
     *
     * Activation logic:
     *   - Reads 'active', 'canPublish', 'state', 'status' fields from the response.
     *   - Only marks activationStatus = 'active' if Proppit explicitly confirms it.
     *   - Defaults to 'pending_activation' if no confirmation is present.
     *   - This reflects reality: publisher received ≠ publisher enabled.
     *
     * Proppit may return any of these identifiers — all are captured:
     *   id, publisherId, externalId, integrationId, agencyId, activationId
     */
    public static function fromArray(array $data): self
    {
        $publisherId = (string) (
            $data['id']
            ?? $data['publisherId']
            ?? $data['publisher_id']
            ?? ''
        );

        $externalId = isset($data['externalId'])
            ? (string) $data['externalId']
            : (isset($data['external_id']) ? (string) $data['external_id'] : null);

        $requestId = isset($data['requestId'])
            ? (string) $data['requestId']
            : (isset($data['request_id']) ? (string) $data['request_id'] : null);

        $message = isset($data['message'])
            ? (string) $data['message']
            : (isset($data['description']) ? (string) $data['description'] : null);

        $activationStatus = self::deriveActivationStatus($data);

        return new self(
            publisherId:      $publisherId,
            name:             (string) ($data['name'] ?? ''),
            email:            isset($data['email']) ? (string) $data['email'] : null,
            phone:            isset($data['phone']) ? (string) $data['phone'] : null,
            raw:              $data,
            activationStatus: $activationStatus,
            externalId:       $externalId,
            requestId:        $requestId,
            message:          $message,
        );
    }

    /**
     * Derives the activation status from the raw Proppit response.
     *
     * Proppit does not yet return a standardized activation field, so this method
     * reads multiple candidate fields. If none confirm active status, it defaults
     * to 'pending_activation' — the safe assumption after create/update.
     */
    private static function deriveActivationStatus(array $data): string
    {
        // Explicit boolean: 'canPublish' or 'can_publish'
        if (isset($data['canPublish'])) {
            return $data['canPublish'] === true ? self::STATUS_ACTIVE : self::STATUS_PENDING_ACTIVATION;
        }
        if (isset($data['can_publish'])) {
            return $data['can_publish'] === true ? self::STATUS_ACTIVE : self::STATUS_PENDING_ACTIVATION;
        }

        // State string from Proppit (non-standardized — may vary)
        $state = strtolower((string) ($data['state'] ?? $data['activationStatus'] ?? $data['activation_status'] ?? ''));
        if ($state !== '') {
            return match ($state) {
                'active', 'enabled', 'activated'        => self::STATUS_ACTIVE,
                'pending', 'pending_activation'          => self::STATUS_PENDING_ACTIVATION,
                'synced'                                  => self::STATUS_SYNCED,
                'rejected', 'disabled'                   => self::STATUS_REJECTED,
                'cannot_publish', 'not_allowed'          => self::STATUS_CANNOT_PUBLISH,
                default                                   => self::STATUS_PENDING_ACTIVATION,
            };
        }

        // Boolean 'active' field
        if (isset($data['active'])) {
            return $data['active'] === true ? self::STATUS_ACTIVE : self::STATUS_PENDING_ACTIVATION;
        }

        // Status string (may double as a generic status)
        $status = strtolower((string) ($data['status'] ?? ''));
        if (in_array($status, ['active', 'enabled', 'activated'], true)) {
            return self::STATUS_ACTIVE;
        }

        // Default: publisher was received by Proppit but activation not confirmed
        return self::STATUS_PENDING_ACTIVATION;
    }
}
