<?php

declare(strict_types=1);

namespace Propit\Laravel;

use GuzzleHttp\Client;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Propit\Api\PropertyApi;
use Propit\Api\PublisherApi;
use Propit\Auth\ClientCredentialsAuthenticator;
use Propit\Config\PropitConfig;
use Propit\Contracts\PropertyApiInterface;
use Propit\Contracts\PropertyPayloadMapperInterface;
use Propit\Contracts\PropitAuthenticatorInterface;
use Propit\Contracts\PropitHttpClientInterface;
use Propit\Contracts\PublisherApiInterface;
use Propit\Http\GuzzlePropitHttpClient;
use Propit\Normalizers\PropertyPayloadNormalizer;
use Propit\Normalizers\PublisherPayloadNormalizer;
use Propit\PropitClient;
use Propit\Support\StructuredLogger;
use Psr\Log\LoggerInterface;

final class PropitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/propit.php', 'propit');

        $this->app->singleton(PropitConfig::class, fn (Container $app): PropitConfig => PropitConfig::fromArray($app['config']->get('propit', [])));

        $this->app->singleton(Client::class, fn (): Client => new Client());

        $this->app->bind(PropitAuthenticatorInterface::class, fn (Container $app): PropitAuthenticatorInterface => new ClientCredentialsAuthenticator(
            config: $app->make(PropitConfig::class),
            client: $app->make(Client::class),
        ));

        $this->app->bind(PropertyPayloadMapperInterface::class, PropertyPayloadNormalizer::class);

        $this->app->bind(PropitHttpClientInterface::class, fn (Container $app): PropitHttpClientInterface => new GuzzlePropitHttpClient(
            client: $app->make(Client::class),
            config: $app->make(PropitConfig::class),
            authenticator: $app->make(PropitAuthenticatorInterface::class),
            logger: new StructuredLogger(
                enabled: $app->make(PropitConfig::class)->structuredLogsEnabled(),
                logger: $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null,
            ),
        ));

        $this->app->bind(PropertyApiInterface::class, fn (Container $app): PropertyApiInterface => new PropertyApi(
            http: $app->make(PropitHttpClientInterface::class),
            mapper: $app->make(PropertyPayloadMapperInterface::class),
            config: $app->make(PropitConfig::class),
        ));

        $this->app->bind(PublisherApiInterface::class, fn (Container $app): PublisherApiInterface => new PublisherApi(
            http: $app->make(PropitHttpClientInterface::class),
            normalizer: new PublisherPayloadNormalizer(),
            config: $app->make(PropitConfig::class),
        ));

        $this->app->singleton(PropitClient::class, fn (Container $app): PropitClient => new PropitClient(
            properties: $app->make(PropertyApiInterface::class),
            publishers: $app->make(PublisherApiInterface::class),
        ));
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/propit.php' => config_path('propit.php'),
        ], 'propit-config');
    }
}
