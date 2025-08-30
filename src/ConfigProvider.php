<?php

declare(strict_types=1);

namespace Modularity;

use Authorization\Repository\PermissionRepository;
use Authorization\Repository\RoleRepository;
use Mezzio\Application;
use Modularity\Authorization\PermissionRepositoryInterface;
use Modularity\Authorization\Repository\NullPermissionRepository;
use Modularity\Authorization\Repository\NullRoleRepository;
use Modularity\Authorization\RoleRepositoryInterface;
use Modularity\Router\AttributeRouteCollector;
use Modularity\Router\AttributeRouteProviderInterface;
use Psr\Container\ContainerInterface;

/**
 * @see ConfigInterface
 */
class ConfigProvider
{
    /**
     * Returns to configuration array.
     *
     * @return array
     */
    public function __invoke()
    {
        return [
            'schema_mapper' => [
                'common_schema_module' => 'Common',
            ],
            'dependencies'  => $this->getDependencyConfig(),
        ];
    }

    /**
     * Return application-level dependency configuration.
     *
     * @return ServiceManagerConfigurationType
     */
    public function getDependencyConfig()
    {
        return [
            'factories' => [
                DataTable\ColumnFiltersInterface::class             => DataTable\ColumnFiltersFactory::class,
                Validation\ValidationErrorFormatterInterface::class => Validation\ValidationErrorFormatterFactory::class,
                Middleware\EntityMiddleware::class                  => Middleware\EntityMiddlewareFactory::class,
                Middleware\ModularityMiddleware::class              => Middleware\ModularityMiddlewareFactory::class,
                AttributeRouteProviderInterface::class              => function (ContainerInterface $container) {
                    return new AttributeRouteCollector(
                        $container->get(Application::class),
                        $container
                    );
                },
                PermissionRepositoryInterface::class                => function ($container) {
                    if ($container->has(PermissionRepository::class)) {
                        return $container->get(PermissionRepository::class);
                    }
                    return new NullPermissionRepository();
                },
                RoleRepositoryInterface::class                      => function ($container) {
                    if ($container->has(RoleRepository::class)) {
                        return $container->get(RoleRepository::class);
                    }
                    return new NullRoleRepository();
                },
            ],
        ];
    }
}
