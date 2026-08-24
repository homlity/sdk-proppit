<?php

declare(strict_types=1);

namespace Proppit\Contracts;

use Proppit\Exceptions\AuthException;

interface ProppitAuthenticatorInterface
{
    /** @throws AuthException */
    public function authenticate(array $headers = []): array;
}
