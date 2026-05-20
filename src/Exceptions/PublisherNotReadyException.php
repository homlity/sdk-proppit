<?php

declare(strict_types=1);

namespace Propit\Exceptions;

/**
 * Thrown when Proppit returns HTTP 403 "Publisher could not publish".
 *
 * This happens when the publisher exists in Proppit but has not yet been
 * manually activated by Proppit support. No code change on the caller side
 * will resolve this — the publisher must be enabled by Proppit.
 *
 * Contact Proppit support with:
 *   - $e->publisherExternalId  (the external ID you used when creating the publisher)
 *   - $e->proppitRequestId     (the Request ID from Proppit's error response)
 */
final class PublisherNotReadyException extends ApiException
{
    public function __construct(
        public readonly string $publisherExternalId,
        public readonly ?string $proppitRequestId,
        array $context = [],
    ) {
        $idHint        = $publisherExternalId !== '' ? " '{$publisherExternalId}'" : '';
        $requestIdHint = $proppitRequestId    !== null ? " (Proppit Request ID: {$proppitRequestId})" : '';

        parent::__construct(
            message: "Publisher{$idHint} is not enabled to publish ads{$requestIdHint}. "
                   . 'The publisher must be manually activated by Proppit support before ads can be created. '
                   . 'Contact Proppit with the publisher external ID and the Request ID.',
            statusCode: 403,
            method: 'POST',
            endpoint: '/ads',
            context: $context,
        );
    }
}
