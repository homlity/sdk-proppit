<?php

declare(strict_types=1);

namespace Propit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Propit\Support\StructuredLogger;

final class StructuredLoggerTest extends TestCase
{
    public function test_sanitizes_sensitive_fields(): void
    {
        $in = ['Authorization' => 'Bearer x', 'api_secret' => 's', 'email' => 'a@b.com'];
        $out = StructuredLogger::sanitize($in);

        self::assertSame('***', $out['Authorization']);
        self::assertSame('***', $out['api_secret']);
        self::assertSame('***', $out['email']);
    }
}
