<?php

declare(strict_types=1);

namespace Proppit;

use Proppit\Contracts\PropertyApiInterface;
use Proppit\Contracts\PublisherApiInterface;

final class ProppitClient
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
