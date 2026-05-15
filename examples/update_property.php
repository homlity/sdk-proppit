<?php

declare(strict_types=1);

require __DIR__ . '/publish_property.php';

try {
    $response = $client->properties()->update('CO-DEMO-1001', $payload);
    print_r(['referenceId' => $response->referenceId, 'status' => $response->status]);
} catch (ValidationException|AuthException|RateLimitException|ApiException $e) {
    echo get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
}
