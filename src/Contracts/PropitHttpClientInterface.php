<?php

declare(strict_types=1);

namespace Propit\Contracts;

use Propit\DTO\HttpResponse;
use Propit\Exceptions\PropitException;

interface PropitHttpClientInterface
{
    /**
     * @throws PropitException
     */
    public function request(string $method, string $uri, array $headers = [], array $query = [], array $json = []): HttpResponse;
}
