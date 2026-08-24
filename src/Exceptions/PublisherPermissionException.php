<?php

declare(strict_types=1);

namespace Propit\Exceptions;

use Propit\Support\StructuredLogger;

/**
 * Thrown when Proppit returns HTTP 403 "Publisher could not publish".
 *
 * This means the publisher was received/created in Proppit but has NOT yet been
 * manually activated/connected by Proppit support. Attempting to publish ads
 * before activation will always fail with this error — no code change resolves it.
 *
 * Proppit must manually enable the publisher after being notified.
 * See docs/publisher-integration.md for the activation request template.
 *
 * Useful fields:
 *   $e->requestId()       — Proppit's request ID from the error response
 *   $e->originalError()   — Raw error string returned by Proppit
 *   $e->rawResponse()     — Sanitized JSON snapshot of the 403 response body
 *   $e->operationalHint() — Human-readable guidance for operators
 */
class PublisherPermissionException extends ApiException
{
    private readonly string $sanitizedRaw;

    public function __construct(
        protected readonly ?string $reqId,
        protected readonly string $errMsg,
        protected readonly array $rawResponseData,
        array $context = [],
    ) {
        $this->sanitizedRaw = (string) json_encode(
            StructuredLogger::sanitize($rawResponseData),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        parent::__construct(
            message: 'El publisher existe o fue recibido por Proppit, pero todavía no está habilitado para publicar. '
                   . 'Debe solicitarse a Proppit la activación/conexión del publisher antes de enviar anuncios.',
            statusCode: 403,
            method: '',
            endpoint: '',
            proppitErrorCode: $errMsg ?: null,
            context: $context,
        );
    }

    public function requestId(): ?string
    {
        return $this->reqId;
    }

    public function originalError(): string
    {
        return $this->errMsg;
    }

    public function rawResponse(): string
    {
        return $this->sanitizedRaw;
    }

    public function operationalHint(): string
    {
        $reqPart = $this->reqId !== null ? " Request ID de Proppit: {$this->reqId}." : '';

        return 'El publisher fue enviado a Proppit pero requiere activación manual por parte del equipo de Proppit.'
             . $reqPart
             . ' Consulta docs/publisher-integration.md para la plantilla de solicitud de activación.';
    }
}
