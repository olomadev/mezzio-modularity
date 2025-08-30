<?php

declare(strict_types=1);

namespace Modularity\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Mezzio\Application;
use Mezzio\MiddlewareFactory;

class ModularityMiddleware implements MiddlewareInterface
{
    private ContainerInterface $container;
    private array $modules;
    private static bool $loaded = false;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->modules = $container->get('config')['modules'] ?? [];
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!self::$loaded) {
            foreach ($this->modules as $module) {
                $class = $module . '\ConfigProvider';
                if (class_exists($class)) {
                    if (method_exists($class, 'registerRoutes')) {
                        $class::registerRoutes($this->container, $module);
                    }
                }
            }
            self::$loaded = true; // run once
        }

        return $handler->handle($request);
    }
}
