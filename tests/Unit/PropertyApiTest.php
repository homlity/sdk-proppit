<?php

declare(strict_types=1);

namespace Propit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Propit\Api\PropertyApi;
use Propit\Config\PropitConfig;
use Propit\Contracts\PropitHttpClientInterface;
use Propit\DTO\HttpResponse;
use Propit\Normalizers\PropertyPayloadNormalizer;

final class PropertyApiTest extends TestCase
{
    public function test_publish_update_find_delete_with_mock_transport(): void
    {
        $http = new class implements PropitHttpClientInterface {
            public function request(string $method, string $uri, array $headers = [], array $query = [], array $json = []): HttpResponse
            {
                return match ($method) {
                    'POST' => new HttpResponse(201, [], '{}', ['referenceId' => 'CO-1', 'status' => 'published']),
                    'PUT' => new HttpResponse(200, [], '{}', ['referenceId' => 'CO-1', 'status' => 'published']),
                    'GET' => new HttpResponse(200, [], '{}', ['referenceId' => 'CO-1', 'status' => 'published']),
                    'DELETE' => new HttpResponse(200, [], '{}', []),
                    default => new HttpResponse(500, [], '{}', []),
                };
            }
        };

        $cfg = PropitConfig::fromArray([
            'base_url' => 'https://real-time.proppit.com/api/v2',
            'api_user' => 'u',
            'api_password' => 'p',
            'publisher_external_id' => 'pub-1',
        ]);

        $api = new PropertyApi($http, new PropertyPayloadNormalizer(), $cfg);

        $payload = [
            'referenceId' => 'CO-1',
            'publisher' => ['externalId' => 'pub-1'],
            'property' => ['type' => 'apartment', 'location' => ['coordinates' => ['lat' => 1, 'long' => 2]]],
            'operations' => [['type' => 'sell', 'price' => ['value' => 10, 'currency' => 'COP']]],
            'title' => ['locale' => 'es-CO', 'text' => 'T'],
            'description' => ['locale' => 'es-CO', 'text' => 'D'],
        ];

        self::assertSame('CO-1', $api->publish($payload)->referenceId);
        self::assertSame('CO-1', $api->update('CO-1', $payload)->referenceId);
        self::assertSame('CO-1', $api->find('CO-1')->referenceId);
        self::assertSame('deleted', $api->delete('CO-1')->status);
    }
}
