<?php

declare(strict_types=1);

namespace Proppit\Exceptions;

/**
 * Thrown when Proppit returns HTTP 403 for reasons other than publisher activation.
 *
 * Use PublisherPermissionException (or its subclass PublisherNotReadyException)
 * for the specific case of "Publisher could not publish".
 */
final class ForbiddenException extends ApiException
{
    public function __construct(string $detail = '', array $context = [])
    {
        $suffix = $detail !== '' ? ": {$detail}" : '.';

        parent::__construct(
            message: "Forbidden response from Proppit{$suffix}",
            statusCode: 403,
            method: '',
            endpoint: '',
            context: $context,
        );
    }
}
