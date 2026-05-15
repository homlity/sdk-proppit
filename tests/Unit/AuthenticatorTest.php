<?php

declare(strict_types=1);

namespace Propit\Tests\Unit;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Propit\Auth\ApiKeySecretAuthenticator;
use Propit\Config\PropitConfig;

final class AuthenticatorTest extends TestCase
{
    public function test_authenticate_adds_bearer_header(): void
    {
        $client = new class implements ClientInterface {
            public function send(\Psr\Http\Message\RequestInterface $request, array $options = []): \Psr\Http\Message\ResponseInterface { throw new \BadMethodCallException(); }
            public function sendAsync(\Psr\Http\Message\RequestInterface $request, array $options = []): \GuzzleHttp\Promise\PromiseInterface { throw new \BadMethodCallException(); }
            public function request($method, $uri, array $options = []): \Psr\Http\Message\ResponseInterface { return new Response(200, [], json_encode(['token' => 'abc', 'expiration' => time() + 3600])); }
            public function requestAsync($method, $uri, array $options = []): \GuzzleHttp\Promise\PromiseInterface { throw new \BadMethodCallException(); }
            public function getConfig($option = null) { return null; }
        };

        $cfg = PropitConfig::fromArray(['base_url' => 'https://real-time.proppit.com/api/v2', 'api_user' => 'u', 'api_password' => 'p']);
        $auth = new ApiKeySecretAuthenticator($cfg, $client);

        $headers = $auth->authenticate();
        self::assertSame('Bearer abc', $headers['Authorization']);
    }
}
