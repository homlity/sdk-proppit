<?php

declare(strict_types=1);

namespace Propit;

use Propit\Contracts\PropertyApiInterface;

final class PropitClient
{
    public function __construct(private readonly PropertyApiInterface $properties)
    {
    }

    public function properties(): PropertyApiInterface
    {
        return $this->properties;
    }
}
