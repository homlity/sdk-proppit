<?php

declare(strict_types=1);

namespace Proppit\Api;

use Proppit\Config\ProppitConfig;
use Proppit\Contracts\PublisherApiInterface;
use Proppit\Contracts\ProppitHttpClientInterface;
use Proppit\DTO\PublisherPayload;
use Proppit\DTO\PublisherResponse;
use Proppit\Exceptions\ApiException;
use Proppit\Normalizers\PublisherPayloadNormalizer;

final class PublisherApi implements PublisherApiInterface
{
    public function __construct(
        private readonly ProppitHttpClientInterface $http,
        private readonly PublisherPayloadNormalizer $normalizer,
        private readonly ProppitConfig $config,
    ) {
    }

    public function create(PublisherPayload $payload): PublisherResponse
    {
        $body     = $this->normalizer->normalize($payload);
        $response = $this->http->request('POST', $this->publishersPath(), json: $body);

        return PublisherResponse::fromArray($response->json);
    }

    public function update(string $publisherId, PublisherPayload $payload): PublisherResponse
    {
        $body     = $this->normalizer->normalize($payload);
        $response = $this->http->request('PUT', $this->publisherPath($publisherId), json: $body);

        return PublisherResponse::fromArray($response->json);
    }

    public function find(string $publisherId): ?PublisherResponse
    {
        try {
            $response = $this->http->request('GET', $this->publisherPath($publisherId));
        } catch (ApiException $e) {
            if ($e->statusCode === 404) {
                return null;
            }
            throw $e;
        }

        if ($response->json === []) {
            return null;
        }

        return PublisherResponse::fromArray($response->json);
    }

    public function createOrUpdate(PublisherPayload $payload): PublisherResponse
    {
        $existing = $this->find($payload->externalId);

        if ($existing !== null) {
            return $this->update($payload->externalId, $payload);
        }

        return $this->create($payload);
    }

    public function status(string $publisherId): PublisherResponse
    {
        return $this->find($publisherId)
            ?? throw new ApiException(
                message: "Publisher '{$publisherId}' not found in Proppit. Run createOrUpdate() to register it first.",
                statusCode: 404,
                method: 'GET',
                endpoint: $this->publisherPath($publisherId),
            );
    }

    private function publishersPath(): string
    {
        return sprintf('/proppit/%s/publishers', $this->config->country());
    }

    private function publisherPath(string $publisherId): string
    {
        return sprintf('/proppit/%s/publishers/%s', $this->config->country(), rawurlencode($publisherId));
    }
}
