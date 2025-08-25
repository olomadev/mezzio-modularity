<?php

declare(strict_types=1);

namespace Modularity\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Entity
{
    public function __construct(
        public string $dto,
        public ?string $entity = null
    ) {
    }
}
