<?php

declare(strict_types=1);

namespace Proppit\Contracts;

use Proppit\DTO\PublisherPayload;
use Proppit\DTO\PublisherResponse;
use Proppit\Exceptions\ProppitException;

interface PublisherApiInterface
{
    /**
     * Creates a new publisher (agency) in Proppit.
     * POST /proppit/{country}/publishers
     *
     * @throws ProppitException
     */
    public function create(PublisherPayload $payload): PublisherResponse;

    /**
     * Updates an existing publisher by its id (external ID).
     * PUT /proppit/{country}/publishers/{id}
     *
     * @throws ProppitException
     */
    public function update(string $publisherId, PublisherPayload $payload): PublisherResponse;

    /**
     * Retrieves a publisher by its id (external ID).
     * GET /proppit/{country}/publishers/{id}
     * Returns null if not found (404).
     *
     * @throws ProppitException
     */
    public function find(string $publisherId): ?PublisherResponse;

    /**
     * Creates the publisher if it does not exist, updates it if it does.
     * Uses find() internally to decide between create() and update().
     *
     * @throws ProppitException
     */
    public function createOrUpdate(PublisherPayload $payload): PublisherResponse;

    /**
     * Fetches the current state of a publisher for diagnostic purposes.
     * GET /proppit/{country}/publishers/{id}
     *
     * The raw array in the returned PublisherResponse contains the full API
     * response, which may include activation/status fields returned by Proppit.
     *
     * Throws ApiException(404) if the publisher does not exist — run
     * createOrUpdate() first.
     *
     * @throws ProppitException
     */
    public function status(string $publisherId): PublisherResponse;
}
