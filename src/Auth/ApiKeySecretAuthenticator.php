<?php

declare(strict_types=1);

namespace Proppit\Auth;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Proppit\Config\ProppitConfig;
use Proppit\Contracts\ProppitAuthenticatorInterface;
use Proppit\Exceptions\AuthException;

final class ApiKeySecretAuthenticator implements ProppitAuthenticatorInterface
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
        try {
            $response = $this->client->request('POST', '/token', [
                'base_uri' => $this->config->baseUrl(),
                'json' => [
                    'user' => $this->config->apiUser(),
                    'password' => $this->config->apiPassword(),
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'User-Agent' => $this->config->userAgent(),
                ],
                'timeout' => $this->config->timeout(),
            ]);

            $data = json_decode((string) $response->getBody(), true);
            if (!is_array($data) || empty($data['token']) || !isset($data['expiration'])) {
                throw new AuthException('Invalid token response from Proppit.', ['status' => $response->getStatusCode()]);
            }

            $this->token = (string) $data['token'];
            $this->expiration = (int) $data['expiration'];
        } catch (GuzzleException $e) {
            throw new AuthException('Could not authenticate against Proppit token endpoint.', [], 0, $e);
        }
    }
}
