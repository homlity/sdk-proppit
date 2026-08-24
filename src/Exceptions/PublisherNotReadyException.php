<?php

declare(strict_types=1);

namespace Proppit\Exceptions;

/**
 * Thrown when Proppit returns HTTP 403 "Publisher could not publish".
 *
 * Extends PublisherPermissionException — catch either class depending on
 * how specific you need to be.
 *
 * This happens when the publisher exists in Proppit but has not yet been
 * manually activated by Proppit support. No code change on the caller side
 * will resolve this — the publisher must be enabled by Proppit.
 *
 * Contact Proppit support with:
 *   - $e->publisherExternalId  (the external ID used when creating the publisher)
 *   - $e->proppitRequestId     (the Request ID from Proppit's error response)
 *   - $e->requestId()          (alias for proppitRequestId — from base class)
 *   - $e->operationalHint()    (human-readable guidance)
 *
 * See docs/publisher-integration.md for the activation request template.
 */
final class PublisherNotReadyException extends PublisherPermissionException
{
    public readonly string $publisherExternalId;
    public readonly ?string $proppitRequestId;

    public function __construct(
        string $publisherExternalId,
        ?string $proppitRequestId,
        array $context = [],
    ) {
        parent::__construct(
            reqId: $proppitRequestId,
            errMsg: 'Publisher could not publish',
            rawResponseData: $context,
            context: $context,
        );

        $this->publisherExternalId = $publisherExternalId;
        $this->proppitRequestId    = $proppitRequestId;
    }
}
