<?php

declare(strict_types=1);

/**
 * update_property.php
 *
 * Updates an existing ad in Proppit (full replace via PUT).
 * All required fields must be resent — the API performs a full replace.
 */

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use Propit\Api\PropertyApi;
use Propit\Api\PublisherApi;
use Propit\Auth\ClientCredentialsAuthenticator;
use Propit\Config\PropitConfig;
use Propit\Exceptions\ApiException;
use Propit\Exceptions\AuthException;
use Propit\Exceptions\RateLimitException;
use Propit\Exceptions\ValidationException;
use Propit\Http\GuzzlePropitHttpClient;
use Propit\Normalizers\PropertyPayloadNormalizer;
use Propit\Normalizers\PublisherPayloadNormalizer;
use Propit\PropitClient;
use Propit\Support\StructuredLogger;

// ── 1. Configuration ─────────────────────────────────────────────────────────

$config = PropitConfig::fromArray([
    'base_url'       => getenv('PROPIT_BASE_URL')      ?: 'https://real-time.proppit.com/api/v2',
    'client_id'      => getenv('PROPIT_CLIENT_ID')     ?: '',
    'client_secret'  => getenv('PROPIT_CLIENT_SECRET') ?: '',
    'country'        => getenv('PROPIT_COUNTRY')       ?: 'CO',
    'timeout'        => (int) (getenv('PROPIT_TIMEOUT')        ?: 30),
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

// ── 3. The referenceId to update ─────────────────────────────────────────────

$referenceId         = getenv('PROPIT_REFERENCE_ID')         ?: 'CO-DEMO-1001';
$publisherExternalId = getenv('PROPIT_PUBLISHER_EXTERNAL_ID') ?: 'homlity_agency_123';

// ── 4. Updated payload ───────────────────────────────────────────────────────

$payload = [
    'referenceId' => $referenceId,
    'publisher'   => ['externalId' => $publisherExternalId],
    'contact'     => ['name' => 'Demo Agent (Updated)', 'email' => 'agent@example.com', 'phone' => '+5712345678'],
    'property'    => [
        'type'     => 'apartment',
        'location' => [
            'countryCode' => 'CO',
            'visibility'  => 'accurate',
            'coordinates' => ['lat' => 4.711, 'long' => -74.0721],
            'address'     => 'Calle 100 # 19-61',
            'geo'         => [
                ['name' => 'Bogotá D.C.', 'level' => 'locality'],
                ['name' => 'Chapinero',   'level' => 'neighborhood'],
            ],
        ],
        'communityFees' => ['value' => 350000, 'currency' => 'COP'],
    ],
    'operations'  => [['type' => 'sell', 'price' => ['value' => 480_000_000, 'currency' => 'COP']]],
    'title'       => ['locale' => 'es-CO', 'text' => 'Apartamento actualizado en Chapinero'],
    'description' => ['locale' => 'es-CO', 'text' => 'Precio actualizado. Excelente apartamento con vista panorámica.'],
    'floorArea'   => ['value' => 82.0, 'unit' => 'sqm'],
    'bedrooms'    => 3,
    'bathrooms'   => 2,
    'parkingSpaces' => 1,
    'stratum'     => 4,
    'condition'   => 'excellent',
    'multimedia'  => [
        'pictures' => [
            ['url' => 'https://images.proppit.com/ingester/demo-updated-1.jpg'],
            ['url' => 'https://images.proppit.com/ingester/demo-updated-2.jpg'],
        ],
    ],
];

// ── 5. Execute ───────────────────────────────────────────────────────────────

try {
    $response = $client->properties()->update($referenceId, $payload);

    echo "Ad updated successfully.\n";
    echo "referenceId : {$response->referenceId}\n";
    echo "status      : {$response->status}\n";
} catch (ValidationException $e) {
    echo "[ValidationException] " . $e->getMessage() . "\n";
    exit(1);
} catch (AuthException $e) {
    echo "[AuthException] " . $e->getMessage() . "\n";
    echo "Check PROPIT_CLIENT_ID and PROPIT_CLIENT_SECRET.\n";
    exit(1);
} catch (RateLimitException $e) {
    $retry = $e->retryAfter ?? 'unknown';
    echo "[RateLimitException] Rate limit reached. Retry after {$retry}s.\n";
    exit(1);
} catch (ApiException $e) {
    echo "[ApiException] HTTP {$e->statusCode} on {$e->method} {$e->endpoint}: {$e->getMessage()}\n";
    exit(1);
}
