<?php

declare(strict_types=1);

namespace Proppit\Auth;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Proppit\Config\ProppitConfig;
use Proppit\Contracts\ProppitAuthenticatorInterface;
use Proppit\Exceptions\AuthException;

/**
 * Obtains a Bearer token from POST /token using system-level credentials
 * (PROPPIT_CLIENT_ID / PROPPIT_CLIENT_SECRET). These credentials represent
 * Homlity as an API client — they are NOT per-agency credentials.
 *
 * The token is cached in-memory for the lifetime of this instance and renewed
 * automatically 30 seconds before expiry. The client_secret is never logged.
 */
final class ClientCredentialsAuthenticator implements ProppitAuthenticatorInterface
{
    private ?string $token = null;
    private int $expiration = 0;

    public function __construct(
        private readonly ProppitConfig $config,
        private readonly ClientInterface $client,
    ) {
    }

    public function authenticate(array $headers = []): array
    {
        if ($this->token === null || time() >= ($this->expiration - 30)) {
            $this->requestToken();
        }

        return array_merge($headers, [
            'Authorization' => 'Bearer ' . $this->token,
        ]);
    }

    private function requestToken(): void
    {
        // Per OpenAPI spec POST /token accepts {"user": "...", "password": "..."}
        // PROPPIT_CLIENT_ID maps to "user" and PROPPIT_CLIENT_SECRET maps to "password".
        try {
            $response = $this->client->request('POST', rtrim($this->config->baseUrl(), '/') . '/token', [
                'json' => [
                    'user'     => $this->config->clientId(),
                    'password' => $this->config->clientSecret(),
                ],
                'headers' => [
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                    'User-Agent'   => $this->config->userAgent(),
                ],
                'timeout' => $this->config->timeout(),
            ]);
        } catch (GuzzleException $e) {
            throw new AuthException(
                'Could not connect to Proppit token endpoint: ' . $e->getMessage(),
                ['guzzle_class' => get_class($e)],
                0,
                $e,
            );
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode === 401 || $statusCode === 403) {
            throw new AuthException(
                'Proppit rejected the client credentials. Verify PROPPIT_CLIENT_ID and PROPPIT_CLIENT_SECRET.',
                ['status' => $statusCode],
                $statusCode,
            );
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new AuthException(
                'Unexpected response from Proppit token endpoint.',
                ['status' => $statusCode],
                $statusCode,
            );
        }

        $data = json_decode((string) $response->getBody(), true);

        if (!is_array($data) || empty($data['token']) || !isset($data['expiration'])) {
            throw new AuthException(
                'Invalid token response from Proppit (missing token or expiration).',
                ['status' => $statusCode],
            );
        }

        $this->token      = (string) $data['token'];
        $this->expiration = (int) $data['expiration'];
    }
}
