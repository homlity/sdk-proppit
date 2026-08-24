<?php

declare(strict_types=1);

namespace Proppit\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Proppit\Config\ProppitConfig;
use Proppit\Exceptions\AuthException;

final class ProppitConfigTest extends TestCase
{
    public function test_config_normalizes_base_url(): void
    {
        $cfg = ProppitConfig::fromArray([
            'base_url'      => 'https://real-time.proppit.com/api/v2/',
            'client_id'     => 'u',
            'client_secret' => 'p',
        ]);

        self::assertSame('https://real-time.proppit.com/api/v2', $cfg->baseUrl());
    }

    public function test_missing_client_id_throws_AuthException(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessageMatches('/PROPPIT_CLIENT_ID/');

        ProppitConfig::fromArray([
            'base_url'      => 'https://real-time.proppit.com/api/v2',
            'client_secret' => 'secret',
        ]);
    }

    public function test_missing_client_secret_throws_AuthException(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessageMatches('/PROPPIT_CLIENT_SECRET/');

        ProppitConfig::fromArray([
            'base_url'  => 'https://real-time.proppit.com/api/v2',
            'client_id' => 'id',
        ]);
    }

    public function test_missing_credentials_throws(): void
    {
        $this->expectException(AuthException::class);

        ProppitConfig::fromArray(['base_url' => 'https://real-time.proppit.com/api/v2']);
    }

    public function test_invalid_base_url_throws_InvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ProppitConfig::fromArray([
            'base_url'      => 'not-a-url',
            'client_id'     => 'id',
            'client_secret' => 'secret',
        ]);
    }

    public function test_legacy_api_user_maps_to_client_id(): void
    {
        $cfg = ProppitConfig::fromArray([
            'base_url'    => 'https://real-time.proppit.com/api/v2',
            'api_user'    => 'legacy-user',
            'api_password' => 'legacy-pass',
        ]);

        self::assertSame('legacy-user', $cfg->clientId());
        self::assertSame('legacy-pass', $cfg->clientSecret());
    }
}
