<?php

declare(strict_types=1);

namespace Propit\Tests\Integration;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Propit\Contracts\PropertyApiInterface;
use Propit\Contracts\PropitAuthenticatorInterface;
use Propit\Contracts\PropitHttpClientInterface;
use Propit\Laravel\PropitServiceProvider;
use Propit\PropitClient;

final class ContainerBindingTest extends TestCase
{
    public function test_service_provider_binds_main_contracts(): void
    {
        $app = new Container();
        $app->instance('config', new Repository(['propit' => [
            'base_url' => 'https://real-time.proppit.com/api/v2',
            'api_user' => 'u',
            'api_password' => 'p',
        ]]));

        $provider = new PropitServiceProvider($app);
        $provider->register();

        self::assertInstanceOf(PropitClient::class, $app->make(PropitClient::class));
        self::assertInstanceOf(PropertyApiInterface::class, $app->make(PropertyApiInterface::class));
        self::assertInstanceOf(PropitAuthenticatorInterface::class, $app->make(PropitAuthenticatorInterface::class));
        self::assertInstanceOf(PropitHttpClientInterface::class, $app->make(PropitHttpClientInterface::class));
    }
}
