<?php

declare(strict_types=1);

namespace Modularity\Router;

use Mezzio\Application;
use Modularity\Attribute\Route;
use Psr\Container\ContainerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use ReflectionClass;
use RegexIterator;
use RuntimeException;

use function class_exists;
use function count;
use function current;
use function explode;
use function file_exists;
use function file_put_contents;
use function filemtime;
use function is_array;
use function is_dir;
use function max;
use function opcache_get_status;
use function str_replace;
use function strtolower;
use function var_export;

class AttributeRouteCollector implements AttributeRouteProviderInterface
{
    /** @var string Cache file path for the current module */
    private string $cacheFile;

    /** @var array<string, array>|null Stores routes cached in OPcache per module */
    private static array $opcacheRoutesByModule = [];

    public function __construct(
        private Application $app,
        private ContainerInterface $container,
        private string $modulesBasePath = APP_ROOT . '/src/',
        private string $cacheDir = APP_ROOT . '/data/cache'
    ) {
    }

    /**
     * Register routes for a single module.
     * Uses module-specific file cache and OPcache memory to avoid duplicates.
     */
    public function registerRoutes(string $moduleName): void
    {
        $moduleKey       = strtolower($moduleName);
        $this->cacheFile = $this->cacheDir . '/routes.' . $moduleKey . '.cache.php';
        $handlerPath     = $this->modulesBasePath . $moduleName . "/src/Handler/";

        if (! is_dir($handlerPath)) {
            throw new RuntimeException("Handler folder not found at: $moduleName/src/");
        }

        $files        = $this->findHandlerFiles($handlerPath);
        $lastModified = $this->getLastModified($files);

        // 1. Check OPcache memory first
        if ($this->canUseOpcache()) {
            if (
                isset(self::$opcacheRoutesByModule[$moduleKey]) &&
                self::$opcacheRoutesByModule[$moduleKey]['_lastModified'] === $lastModified
            ) {
                $this->registerFromCache(self::$opcacheRoutesByModule[$moduleKey]);
                return;
            }

            if ($this->isCacheValid($lastModified)) {
                $routes                                  = include $this->cacheFile;
                self::$opcacheRoutesByModule[$moduleKey] = $routes;
                $this->registerFromCache($routes);
                return;
            }
        }

        // 2. OPcache not available but cache file is valid
        if (! $this->canUseOpcache() && $this->isCacheValid($lastModified)) {
            $routes = include $this->cacheFile;
            $this->registerFromCache($routes);
            return;
        }

        // 3. Cache invalid → generate routes, write cache, then register
        $routes = $this->generateRoutes($files);
        $this->writeCache($routes, $lastModified);

        $cacheData = [
            '_lastModified' => $lastModified,
            'data'          => $routes,
        ];

        // Register generated routes
        $this->registerFromCache($cacheData);

        // Store in OPcache memory if available
        if ($this->canUseOpcache()) {
            self::$opcacheRoutesByModule[$moduleKey] = $cacheData;
        }
    }

    /**
     * Recursively find all handler PHP files in the module.
     */
    private function findHandlerFiles(string $handlerPath): array
    {
        $iterator   = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($handlerPath));
        $foundFiles = new RegexIterator($iterator, '/^.+Handler\.php$/i', RecursiveRegexIterator::GET_MATCH);

        $files = [];
        foreach ($foundFiles as $file) {
            $files[] = current($file);
        }
        return $files;
    }

    /**
     * Get the most recent file modification timestamp.
     */
    private function getLastModified(array $files): int
    {
        $times = [];
        foreach ($files as $file) {
            $times[] = filemtime($file);
        }
        return max($times);
    }

    /**
     * Check if the cache file is still valid.
     */
    private function isCacheValid(int $lastModified): bool
    {
        if (! file_exists($this->cacheFile)) {
            return false;
        }
        $data = include $this->cacheFile;
        return isset($data['_lastModified']) && $data['_lastModified'] === $lastModified;
    }

    /**
     * Generate route definitions from handler classes and their Route attributes.
     */
    private function generateRoutes(array $files): array
    {
        $routes = [];
        foreach ($files as $file) {
            $class = $this->resolveNamespace($file);
            if (! class_exists($class)) {
                continue;
            }

            $ref        = new ReflectionClass($class);
            $attributes = $ref->getAttributes(Route::class);

            if (count($attributes) === 0) {
                continue;
            }

            foreach ($attributes as $attribute) {
                /** @var Route $route */
                $route = $attribute->newInstance();

                $namespaceParts = explode('\\', $ref->getNamespaceName());
                $module         = $namespaceParts[0] ?? 'UnknownModule';
                $pipeline       = [...$route->middlewares, $class];
                $meta           = $route->meta ?? [];

                if (! isset($meta['module'])) {
                    $meta['module'] = $module;
                }

                $routes[] = [
                    'path'     => $route->path,
                    'pipeline' => $pipeline,
                    'methods'  => $route->methods,
                    'options'  => ['meta' => $meta],
                ];
            }
        }
        return $routes;
    }

    /**
     * Register routes into the Mezzio application from cache or generated data.
     */
    private function registerFromCache(array $routes): void
    {
        foreach ($routes['data'] as $r) {
            $this->app->route($r['path'], $r['pipeline'], $r['methods'])
                ->setOptions($r['options']);
        }
    }

    /**
     * Write routes to the cache file.
     */
    private function writeCache(array $routes, int $lastModified): void
    {
        $data = [
            '_lastModified' => $lastModified,
            'data'          => $routes,
        ];

        file_put_contents(
            $this->cacheFile,
            "<?php\n\nreturn " . var_export($data, true) . ";\n"
        );
    }

    /**
     * Resolve fully qualified class name from a handler file path.
     */
    private function resolveNamespace(string $filePath): ?string
    {
        $basePath = APP_ROOT . '/src/';
        $relative = str_replace([$basePath, '/', '.php'], ['', '\\', ''], $filePath);
        return str_replace('\\src\\', '\\', $relative);
    }

    /**
     * Check if OPcache is enabled.
     */
    private function canUseOpcache(): bool
    {
        $status = opcache_get_status(false);
        return is_array($status) && ! empty($status['opcache_enabled']);
    }
}
