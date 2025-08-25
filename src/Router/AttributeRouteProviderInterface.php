<?php

declare(strict_types=1);

namespace Modularity\Router;

interface AttributeRouteProviderInterface
{
    public function registerRoutes(string $moduleDirectory): void;
}
