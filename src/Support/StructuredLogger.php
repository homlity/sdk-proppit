<?php

declare(strict_types=1);

namespace Propit\Support;

use Psr\Log\LoggerInterface;

final class StructuredLogger
{
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
        $sensitive = ['authorization', 'api_key', 'api_secret', 'password', 'token', 'api_password', 'api_user', 'email', 'phone', 'whatsapp'];
        array_walk_recursive($context, static function (&$value, $key) use ($sensitive): void {
            if (in_array(strtolower((string) $key), $sensitive, true)) {
                $value = '***';
            }
        });

        return $context;
    }
}
