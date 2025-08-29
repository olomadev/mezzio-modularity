<?php

declare(strict_types=1);

namespace Modularity\Validation;

use Laminas\InputFilter\InputFilterInterface;

interface ErrorFormatterInterface
{
    public function format(InputFilterInterface $filter): array;
}
