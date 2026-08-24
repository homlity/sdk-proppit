<?php

declare(strict_types=1);

namespace Proppit\Tests\Unit;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Proppit\Auth\ClientCredentialsAuthenticator;
use Proppit\Config\ProppitConfig;
use Proppit\Exceptions\AuthException;

final class ClientCredentialsAuthenticatorTest extends TestCase
{
    private ProppitConfig $config;

    protected function setUp(): void
    {
        $this->config = ProppitConfig::fromArray([
            'base_url'      => 'https://real-time.proppit.com/api/v2',
            'client_id'     => 'homlity-client-id',
            'client_secret' => 'super-secret',
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function guzzleReturning(int $status, array $body): ClientInterface
    {
        $mock = $this->createMock(ClientInterface::class);
        $mock->method('request')->willReturn(
            new Response($status, ['Content-Type' => 'application/json'], json_encode($body))
        );

        return $mock;
    }

    private function guzzleThrows(\Throwable $e): ClientInterface
    {
        $mock = $this->createMock(ClientInterface::class);
        $mock->method('request')->willThrowException($e);

        return $mock;
    }

    // ── authenticate returns Bearer header ───────────────────────────────────

    public function test_authenticate_returns_Authorization_header_with_bearer_token(): void
    {
        $expiry = time() + 3600;
        $auth   = new ClientCredentialsAuthenticator(
            $this->config,
            $this->guzzleReturning(200, ['token' => 'my-token', 'expiration' => $expiry])
        );

        $headers = $auth->authenticate();

        self::assertArrayHasKey('Authorization', $headers);
        self::assertSame('Bearer my-token', $headers['Authorization']);
    }

    public function test_authenticate_merges_with_existing_headers(): void
    {
        $expiry = time() + 3600;
        $auth   = new ClientCredentialsAuthenticator(
            $this->config,
            $this->guzzleReturning(200, ['token' => 'tok', 'expiration' => $expiry])
        );

        $headers = $auth->authenticate(['Accept' => 'application/json']);

        self::assertSame('application/json', $headers['Accept']);
        self::assertSame('Bearer tok', $headers['Authorization']);
    }

    // ── token is cached ───────────────────────────────────────────────────────

    public function test_token_is_cached_and_guzzle_called_only_once(): void
    {
        $expiry = time() + 3600;
        $mock   = $this->createMock(ClientInterface::class);
        $mock->expects(self::once())
            ->method('request')
            ->willReturn(new Response(200, [], json_encode(['token' => 'cached', 'expiration' => $expiry])));

        $auth = new ClientCredentialsAuthenticator($this->config, $mock);

        $auth->authenticate();
        $auth->authenticate();
    }

    // ── 401 / 403 throw AuthException ────────────────────────────────────────

    public function test_authenticate_throws_AuthException_on_401(): void
    {
        $auth = new ClientCredentialsAuthenticator(
            $this->config,
            $this->guzzleReturning(401, ['error' => 'Unauthorized'])
        );

        $this->expectException(AuthException::class);
        $auth->authenticate();
    }

    public function test_authenticate_throws_AuthException_on_403(): void
    {
        $auth = new ClientCredentialsAuthenticator(
            $this->config,
            $this->guzzleReturning(403, ['error' => 'Forbidden'])
        );

        $this->expectException(AuthException::class);
        $auth->authenticate();
    }

    // ── AuthException must not expose client_secret ───────────────────────────

    public function test_AuthException_message_does_not_contain_client_secret(): void
    {
        $auth = new ClientCredentialsAuthenticator(
            $this->config,
            $this->guzzleReturning(401, ['error' => 'bad credentials'])
        );

        try {
            $auth->authenticate();
            self::fail('AuthException expected');
        } catch (AuthException $e) {
            self::assertStringNotContainsString('super-secret', $e->getMessage());
            self::assertStringNotContainsString('super-secret', serialize($e->context()));
        }
    }

    // ── Invalid token body ────────────────────────────────────────────────────

    public function test_missing_token_field_throws_AuthException(): void
    {
        $auth = new ClientCredentialsAuthenticator(
            $this->config,
            $this->guzzleReturning(200, ['expiration' => time() + 3600])
        );

        $this->expectException(AuthException::class);
        $auth->authenticate();
    }

    public function test_missing_expiration_field_throws_AuthException(): void
    {
        $auth = new ClientCredentialsAuthenticator(
            $this->config,
            $this->guzzleReturning(200, ['token' => 'some-token'])
        );

        $this->expectException(AuthException::class);
        $auth->authenticate();
    }

    // ── transport failure ─────────────────────────────────────────────────────

    public function test_guzzle_exception_is_wrapped_in_AuthException(): void
    {
        $connectEx = new ConnectException('Connection refused', new Request('POST', '/token'));
        $auth      = new ClientCredentialsAuthenticator($this->config, $this->guzzleThrows($connectEx));

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Could not connect to Proppit token endpoint:');
        $auth->authenticate();
    }

    // ── ProppitConfig loads credentials from config array ─────────────────────

    public function test_ProppitConfig_loads_client_id_and_client_secret(): void
    {
        $cfg = ProppitConfig::fromArray([
            'base_url'      => 'https://real-time.proppit.com/api/v2',
            'client_id'     => 'id-123',
            'client_secret' => 'secret-456',
        ]);

        self::assertSame('id-123', $cfg->clientId());
        self::assertSame('secret-456', $cfg->clientSecret());
    }

    public function test_ProppitConfig_loads_legacy_api_user_as_client_id(): void
    {
        $cfg = ProppitConfig::fromArray([
            'base_url'    => 'https://real-time.proppit.com/api/v2',
            'api_user'    => 'legacy-user',
            'api_password' => 'legacy-pass',
        ]);

        self::assertSame('legacy-user', $cfg->clientId());
        self::assertSame('legacy-pass', $cfg->clientSecret());
    }

    public function test_ProppitConfig_throws_AuthException_when_client_id_missing(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessageMatches('/PROPPIT_CLIENT_ID/');

        ProppitConfig::fromArray([
            'base_url'      => 'https://real-time.proppit.com/api/v2',
            'client_secret' => 'secret',
        ]);
    }

    public function test_ProppitConfig_throws_AuthException_when_client_secret_missing(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessageMatches('/PROPPIT_CLIENT_SECRET/');

        ProppitConfig::fromArray([
            'base_url'  => 'https://real-time.proppit.com/api/v2',
            'client_id' => 'id',
        ]);
    }

    public function test_redacted_does_not_expose_full_client_secret(): void
    {
        $redacted = $this->config->redacted();

        self::assertSame('***', $redacted['client_secret']);
        self::assertStringNotContainsString('super-secret', serialize($redacted));
    }

    public function test_redacted_shows_partial_client_id(): void
    {
        $redacted = $this->config->redacted();

        self::assertStringContainsString('homl', $redacted['client_id']);
        self::assertStringContainsString('***', $redacted['client_id']);
        self::assertStringNotContainsString('homlity-client-id', $redacted['client_id']);
    }
}
