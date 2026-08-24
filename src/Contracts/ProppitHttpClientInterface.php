<?php

declare(strict_types=1);

namespace Proppit\Contracts;

use Proppit\DTO\HttpResponse;
use Proppit\Exceptions\ProppitException;

interface ProppitHttpClientInterface
{
    /**
     * @throws ProppitException
     */
    public function request(string $method, string $uri, array $headers = [], array $query = [], array $json = []): HttpResponse;
}
