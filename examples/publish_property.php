<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use Propit\Api\PropertyApi;
use Propit\Auth\ApiKeySecretAuthenticator;
use Propit\Config\PropitConfig;
use Propit\Exceptions\ApiException;
use Propit\Exceptions\AuthException;
use Propit\Exceptions\RateLimitException;
use Propit\Exceptions\ValidationException;
use Propit\Http\GuzzlePropitHttpClient;
use Propit\Normalizers\PropertyPayloadNormalizer;
use Propit\PropitClient;
use Propit\Support\StructuredLogger;

$config = PropitConfig::fromArray([
    'base_url' => getenv('PROPIT_BASE_URL') ?: 'https://real-time.proppit.com/api/v2',
    'api_user' => getenv('PROPIT_API_USER') ?: getenv('PROPIT_API_KEY') ?: '',
    'api_password' => getenv('PROPIT_API_PASSWORD') ?: getenv('PROPIT_API_SECRET') ?: '',
    'country' => getenv('PROPIT_COUNTRY') ?: 'CO',
    'publisher_external_id' => getenv('PROPIT_PUBLISHER_EXTERNAL_ID') ?: null,
]);

$guzzle = new Client();
$auth = new ApiKeySecretAuthenticator($config, $guzzle);
$http = new GuzzlePropitHttpClient($guzzle, $config, $auth, new StructuredLogger(false));
$api = new PropertyApi($http, new PropertyPayloadNormalizer(), $config);
$client = new PropitClient($api);

$payload = [
    'referenceId' => 'CO-DEMO-1001',
    'publisher' => ['externalId' => (string) (getenv('PROPIT_PUBLISHER_EXTERNAL_ID') ?: 'demo-publisher')],
    'contact' => ['name' => 'Demo Agent', 'email' => 'agent@example.com', 'phone' => '+5712345678'],
    'property' => [
        'type' => 'apartment',
        'location' => [
            'countryCode' => 'CO',
            'visibility' => 'accurate',
            'coordinates' => ['lat' => 4.711, 'long' => -74.0721],
        ],
    ],
    'operations' => [['type' => 'sell', 'price' => ['value' => 450000000, 'currency' => 'COP']]],
    'title' => ['locale' => 'es-CO', 'text' => 'Apartamento en venta'],
    'description' => ['locale' => 'es-CO', 'text' => 'Apartamento demo'],
    'multimedia' => ['pictures' => [['url' => 'https://images.proppit.com/ingester/demo.jpg']]],
];

try {
    $response = $client->properties()->publish($payload);
    print_r(['referenceId' => $response->referenceId, 'status' => $response->status]);
} catch (ValidationException|AuthException|RateLimitException|ApiException $e) {
    echo get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
}
