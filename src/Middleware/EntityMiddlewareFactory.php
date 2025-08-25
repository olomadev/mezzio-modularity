<?php

declare(strict_types=1);

namespace Modularity\Middleware;

use Laminas\Db\Adapter\AdapterInterface;
use Laminas\InputFilter\InputFilterPluginManager;
use Modularity\Validation\ValidationErrorFormatterInterface;
use Psr\Container\ContainerInterface;

class EntityMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): EntityMiddleware
    {
        return new EntityMiddleware(
            $container->get(InputFilterPluginManager::class),
            $container->get(ValidationErrorFormatterInterface::class),
            $container->get(AdapterInterface::class),
        );
    }
}
