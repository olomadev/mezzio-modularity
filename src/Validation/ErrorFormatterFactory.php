<?php

declare(strict_types=1);

namespace Modularity\Validation;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class ErrorFormatterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        return new ErrorFormatter(
            [
                'response_key'   => 'data',
                'multiple_error' => true,
            ]
        );
    }
}
