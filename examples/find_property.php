<?php

declare(strict_types=1);

/**
 * find_property.php
 *
 * Retrieves an ad from Proppit by referenceId.
 * Requires PROPIT_PUBLISHER_EXTERNAL_ID (the agency's externalId in Proppit).
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
    'base_url'               => getenv('PROPIT_BASE_URL')               ?: 'https://real-time.proppit.com/api/v2',
    'client_id'              => getenv('PROPIT_CLIENT_ID')              ?: '',
    'client_secret'          => getenv('PROPIT_CLIENT_SECRET')          ?: '',
    'country'                => getenv('PROPIT_COUNTRY')                ?: 'CO',
    'timeout'                => (int) (getenv('PROPIT_TIMEOUT')         ?: 30),
    'retry_attempts'         => (int) (getenv('PROPIT_RETRY_ATTEMPTS')  ?: 3),
    'publisher_external_id'  => getenv('PROPIT_PUBLISHER_EXTERNAL_ID') ?: null,
]);

// ── 2. Build the stack ───────────────────────────────────────────────────────

$guzzle = new Client();
$auth   = new ClientCredentialsAuthenticator($config, $guzzle);
$http   = new GuzzlePropitHttpClient($guzzle, $config, $auth, new StructuredLogger(false));

$client = new PropitClient(
    properties: new PropertyApi($http, new PropertyPayloadNormalizer(), $config),
    publishers: new PublisherApi($http, new PublisherPayloadNormalizer(), $config),
);

// ── 3. Fetch by referenceId ──────────────────────────────────────────────────
// GET /proppit/{country}/ads/{referenceId}?externalId={publisherExternalId}
// Requires PROPIT_PUBLISHER_EXTERNAL_ID to be set.

$referenceId = getenv('PROPIT_REFERENCE_ID') ?: 'CO-DEMO-1001';

try {
    $response = $client->properties()->find($referenceId);

    echo "Ad found.\n";
    echo "referenceId : {$response->referenceId}\n";
    echo "status      : {$response->status}\n";
    echo "raw data    :\n";
    print_r($response->data);
} catch (ValidationException $e) {
    echo "[ValidationException] " . $e->getMessage() . "\n";
    echo "Set PROPIT_PUBLISHER_EXTERNAL_ID to the agency's externalId (e.g. homlity_agency_123).\n";
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
    if ($e->statusCode === 404) {
        echo "Ad '{$referenceId}' not found or not accessible with the configured publisher.\n";
    } else {
        echo "[ApiException] HTTP {$e->statusCode} on {$e->method} {$e->endpoint}: {$e->getMessage()}\n";
    }
    exit(1);
}

// ── Optional: look up without needing publisher_external_id in config ────────

echo "\n--- findByExternalId example ---\n";

$publisherExternalId = getenv('PROPIT_PUBLISHER_EXTERNAL_ID') ?: 'homlity_agency_123';
$result              = $client->properties()->findByExternalId($publisherExternalId, $referenceId);

if ($result === null) {
    echo "Ad not found via findByExternalId.\n";
} else {
    echo "Found: {$result->referenceId} (status: {$result->status})\n";
}
