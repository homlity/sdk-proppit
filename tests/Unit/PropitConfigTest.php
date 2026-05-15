<?php

declare(strict_types=1);

namespace Propit\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Propit\Config\PropitConfig;

final class PropitConfigTest extends TestCase
{
    public function test_config_normalizes_base_url(): void
    {
        $cfg = PropitConfig::fromArray([
            'base_url' => 'https://real-time.proppit.com/api/v2/',
            'api_user' => 'u',
            'api_password' => 'p',
        ]);

        self::assertSame('https://real-time.proppit.com/api/v2', $cfg->baseUrl());
    }

    public function test_missing_credentials_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PropitConfig::fromArray(['base_url' => 'https://real-time.proppit.com/api/v2']);
    }
}
