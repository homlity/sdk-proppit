<?php

declare(strict_types=1);

/**
 * publish_property.php
 *
 * Creates a new ad in Proppit linked to a publisher (agency).
 *
 * Prerequisites:
 *   - PROPIT_CLIENT_ID and PROPIT_CLIENT_SECRET set in .env
 *   - The publisher has been registered via sync_publisher.php
 *   - PROPIT_PUBLISHER_EXTERNAL_ID set to the agency's externalId
 *     (e.g. homlity_agency_123)
 */

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use Propit\Api\PropertyApi;
use Propit\Api\PublisherApi;
use Propit\Auth\ClientCredentialsAuthenticator;
use Propit\Config\PropitConfig;
use Propit\Exceptions\ApiException;
use Propit\Exceptions\AuthException;
use Propit\Exceptions\PublisherNotReadyException;
use Propit\Exceptions\RateLimitException;
use Propit\Exceptions\ValidationException;
use Propit\Http\GuzzlePropitHttpClient;
use Propit\Normalizers\PropertyPayloadNormalizer;
use Propit\Normalizers\PublisherPayloadNormalizer;
use Propit\PropitClient;
use Propit\Support\StructuredLogger;

// ── 1. Configuration ─────────────────────────────────────────────────────────

$config = PropitConfig::fromArray([
    'base_url'      => getenv('PROPIT_BASE_URL') ?: 'https://real-time.proppit.com/api/v2',
    'client_id'     => getenv('PROPIT_CLIENT_ID') ?: '',
    'client_secret' => getenv('PROPIT_CLIENT_SECRET') ?: '',
    'country'       => getenv('PROPIT_COUNTRY') ?: 'CO',
    'timeout'       => (int) (getenv('PROPIT_TIMEOUT') ?: 30),
    'retry_attempts' => (int) (getenv('PROPIT_RETRY_ATTEMPTS') ?: 3),
]);

// ── 2. Build the stack ───────────────────────────────────────────────────────

$guzzle = new Client();
$auth   = new ClientCredentialsAuthenticator($config, $guzzle);
$http   = new GuzzlePropitHttpClient($guzzle, $config, $auth, new StructuredLogger(false));

$client = new PropitClient(
    properties: new PropertyApi($http, new PropertyPayloadNormalizer(), $config),
    publishers: new PublisherApi($http, new PublisherPayloadNormalizer(), $config),
);

// ── 3. Build the ad payload ──────────────────────────────────────────────────
// publisher.externalId must be the same externalId used when creating the publisher.
// In Homlity this is stored as inmobiliaria.proppit_external_id.

$publisherExternalId = getenv('PROPIT_PUBLISHER_EXTERNAL_ID') ?: 'homlity_agency_123';

$payload = [
    'referenceId' => 'CO-DEMO-1001',
    'publisher'   => ['externalId' => $publisherExternalId],
    'contact'     => ['name' => 'Demo Agent', 'email' => 'agent@example.com', 'phone' => '+5712345678'],
    'property'    => [
        'type'     => 'apartment',
        'location' => [
            'countryCode' => 'CO',
            'visibility'  => 'accurate',
            'coordinates' => ['lat' => 4.711, 'long' => -74.0721],
        ],
    ],
    'operations'  => [['type' => 'sell', 'price' => ['value' => 450_000_000, 'currency' => 'COP']]],
    'title'       => ['locale' => 'es-CO', 'text' => 'Apartamento en venta'],
    'description' => ['locale' => 'es-CO', 'text' => 'Apartamento demo en Bogotá.'],
    'multimedia'  => ['pictures' => [['url' => 'https://images.proppit.com/ingester/demo.jpg']]],
];

// ── 4. Publish ───────────────────────────────────────────────────────────────

try {
    $response = $client->properties()->publish($payload);
    echo "Ad published.\n";
    echo "referenceId : {$response->referenceId}\n";
    echo "status      : {$response->status}\n";
} catch (PublisherNotReadyException $e) {
    echo "[PublisherNotReadyException] {$e->getMessage()}\n";
    echo "\nContact Proppit support with:\n";
    echo "  Publisher external ID : {$e->publisherExternalId}\n";
    if ($e->proppitRequestId !== null) {
        echo "  Request ID            : {$e->proppitRequestId}\n";
    }
    echo "\nWhile waiting for activation you can check the publisher state:\n";
    echo "  \$client->publishers()->status('{$e->publisherExternalId}');\n";
    exit(1);
} catch (ValidationException $e) {
    echo "[ValidationException] " . $e->getMessage() . "\n";
    exit(1);
} catch (AuthException $e) {
    echo "[AuthException] " . $e->getMessage() . "\n";
    echo "Check PROPIT_CLIENT_ID and PROPIT_CLIENT_SECRET.\n";
    exit(1);
} catch (RateLimitException $e) {
    $retry = $e->retryAfter ?? 'unknown';
    echo "[RateLimitException] Retry after {$retry}s.\n";
    exit(1);
} catch (ApiException $e) {
    echo "[ApiException] HTTP {$e->statusCode} on {$e->method} {$e->endpoint}: {$e->getMessage()}\n";
    exit(1);
}
