<?php

declare(strict_types=1);

namespace Propit;

use Propit\Contracts\PropertyApiInterface;
use Propit\Contracts\PublisherApiInterface;

final class PropitClient
{
    public function __construct(
        private readonly PropertyApiInterface $properties,
        private readonly PublisherApiInterface $publishers,
    ) {
    }

    public function properties(): PropertyApiInterface
    {
        return $this->properties;
    }

    public function publishers(): PublisherApiInterface
    {
        return $this->publishers;
    }
}
