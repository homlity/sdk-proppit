<?php

declare(strict_types=1);

namespace Propit\Api;

use Propit\Config\PropitConfig;
use Propit\Contracts\PropertyApiInterface;
use Propit\Contracts\PropertyPayloadMapperInterface;
use Propit\Contracts\PropitHttpClientInterface;
use Propit\DTO\PropertyPayload;
use Propit\DTO\PropertyResponse;
use Propit\Exceptions\ValidationException;

final class PropertyApi implements PropertyApiInterface
{
    public function __construct(
        private readonly PropitHttpClientInterface $http,
        private readonly PropertyPayloadMapperInterface $mapper,
        private readonly PropitConfig $config,
    ) {
    }

    public function publish(PropertyPayload|array $payload): PropertyResponse
    {
        $body = $this->mapper->normalize($payload);
        $response = $this->http->request('POST', $this->adsPath(), json: $body);

        return PropertyResponse::fromArray($response->json);
    }

    public function update(string $referenceId, PropertyPayload|array $payload): PropertyResponse
    {
        $body = $this->mapper->normalize($payload);
        $response = $this->http->request('PUT', $this->adPath($referenceId), json: $body);

        return PropertyResponse::fromArray($response->json);
    }

    public function find(string $referenceId): PropertyResponse
    {
        $externalId = $this->config->publisherExternalId();
        if ($externalId === null || $externalId === '') {
            throw new ValidationException('PROPIT_PUBLISHER_EXTERNAL_ID is required for find/delete operations.');
        }

        return $this->findByExternalId($externalId, $referenceId)
            ?? throw new ValidationException('Proppit returned empty ad response.');
    }

    public function findByExternalId(string $externalId, string $referenceId): ?PropertyResponse
    {
        $response = $this->http->request('GET', $this->adPath($referenceId), query: ['externalId' => $externalId]);

        if ($response->json === []) {
            return null;
        }

        return PropertyResponse::fromArray($response->json);
    }

    public function delete(string $referenceId): PropertyResponse
    {
        $externalId = $this->config->publisherExternalId();
        if ($externalId === null || $externalId === '') {
            throw new ValidationException('PROPIT_PUBLISHER_EXTERNAL_ID is required for find/delete operations.');
        }

        $response = $this->http->request('DELETE', $this->adPath($referenceId), query: ['externalId' => $externalId]);

        return PropertyResponse::fromArray($response->json + ['referenceId' => $referenceId, 'status' => 'deleted']);
    }

    private function adsPath(): string
    {
        return sprintf('/proppit/%s/ads', $this->config->country());
    }

    private function adPath(string $referenceId): string
    {
        return sprintf('/proppit/%s/ads/%s', $this->config->country(), rawurlencode($referenceId));
    }
}
