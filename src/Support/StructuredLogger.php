<?php

declare(strict_types=1);

namespace Proppit\Support;

use Psr\Log\LoggerInterface;

final class StructuredLogger
{
    // Fields whose values are fully redacted in logs.
    private const REDACT_FULL = [
        'authorization',
        'client_secret',
        'api_secret',
        'api_password',
        'password',
        'token',
        'access_token',
        'refresh_token',
        'bearer',
        'signature',
        'secret',
        'email',
        'phone',
        'whatsapp',
    ];

    // Fields whose values are partially redacted (first 4 chars + ***).
    private const REDACT_PARTIAL = [
        'client_id',
        'api_key',
        'api_user',
    ];

    public function __construct(
        private readonly bool $enabled,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function info(string $event, array $context = []): void
    {
        if ($this->enabled && $this->logger !== null) {
            $this->logger->info($event, self::sanitize($context));
        }
    }

    public static function sanitize(array $context): array
    {
        array_walk_recursive($context, static function (&$value, $key): void {
            $lower = strtolower((string) $key);

            if (\in_array($lower, self::REDACT_FULL, true)) {
                $value = '***';
                return;
            }

            if (\in_array($lower, self::REDACT_PARTIAL, true) && \is_string($value) && \strlen($value) > 0) {
                $value = substr($value, 0, 4) . '***';
            }
        });

        return $context;
    }
}
