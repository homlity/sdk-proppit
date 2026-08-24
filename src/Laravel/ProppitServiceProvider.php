<?php

declare(strict_types=1);

namespace Proppit\Laravel;

use GuzzleHttp\Client;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Proppit\Api\PropertyApi;
use Proppit\Api\PublisherApi;
use Proppit\Auth\ClientCredentialsAuthenticator;
use Proppit\Config\ProppitConfig;
use Proppit\Contracts\PropertyApiInterface;
use Proppit\Contracts\PropertyPayloadMapperInterface;
use Proppit\Contracts\ProppitAuthenticatorInterface;
use Proppit\Contracts\ProppitHttpClientInterface;
use Proppit\Contracts\PublisherApiInterface;
use Proppit\Http\GuzzleProppitHttpClient;
use Proppit\Normalizers\PropertyPayloadNormalizer;
use Proppit\Normalizers\PublisherPayloadNormalizer;
use Proppit\ProppitClient;
use Proppit\Support\StructuredLogger;
use Psr\Log\LoggerInterface;

final class ProppitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/proppit.php', 'proppit');

        $this->app->singleton(ProppitConfig::class, fn (Container $app): ProppitConfig => ProppitConfig::fromArray($app['config']->get('proppit', [])));

        $this->app->singleton(Client::class, fn (): Client => new Client());

        $this->app->bind(ProppitAuthenticatorInterface::class, fn (Container $app): ProppitAuthenticatorInterface => new ClientCredentialsAuthenticator(
            config: $app->make(ProppitConfig::class),
            client: $app->make(Client::class),
        ));

        $this->app->bind(PropertyPayloadMapperInterface::class, PropertyPayloadNormalizer::class);

        $this->app->bind(ProppitHttpClientInterface::class, fn (Container $app): ProppitHttpClientInterface => new GuzzleProppitHttpClient(
            client: $app->make(Client::class),
            config: $app->make(ProppitConfig::class),
            authenticator: $app->make(ProppitAuthenticatorInterface::class),
            logger: new StructuredLogger(
                enabled: $app->make(ProppitConfig::class)->structuredLogsEnabled(),
                logger: $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null,
            ),
        ));

        $this->app->bind(PropertyApiInterface::class, fn (Container $app): PropertyApiInterface => new PropertyApi(
            http: $app->make(ProppitHttpClientInterface::class),
            mapper: $app->make(PropertyPayloadMapperInterface::class),
            config: $app->make(ProppitConfig::class),
        ));

        $this->app->bind(PublisherApiInterface::class, fn (Container $app): PublisherApiInterface => new PublisherApi(
            http: $app->make(ProppitHttpClientInterface::class),
            normalizer: new PublisherPayloadNormalizer(),
            config: $app->make(ProppitConfig::class),
        ));

        $this->app->singleton(ProppitClient::class, fn (Container $app): ProppitClient => new ProppitClient(
            properties: $app->make(PropertyApiInterface::class),
            publishers: $app->make(PublisherApiInterface::class),
        ));
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/proppit.php' => config_path('proppit.php'),
        ], 'proppit-config');
    }
}
