<?php

declare(strict_types=1);

namespace Modularity\Middleware;

use Psr\Container\ContainerInterface;

class ModularityMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): ModularityMiddleware
    {
        return new ModularityMiddleware($container);
    }
}
