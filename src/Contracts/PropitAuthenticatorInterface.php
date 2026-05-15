<?php

declare(strict_types=1);

namespace Propit\Contracts;

use Propit\Exceptions\AuthException;

interface PropitAuthenticatorInterface
{
    /** @throws AuthException */
    public function authenticate(array $headers = []): array;
}
