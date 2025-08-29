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

use function basename;
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
    private string $cacheFile;

    /** @var array|null */
    private static ?array $opcacheRoutes = null;

    public function __construct(
        private Application $app,
        private ContainerInterface $container,
        private string $modulesBasePath = APP_ROOT . '/src/',
        string $cacheDir = APP_ROOT . '/data/cache'
    ) {
        $this->cacheFile = $cacheDir . '/routes.cache.php';
    }

    public function registerRoutes(string $dir): void
    {
        $moduleName      = basename($dir);
        $handlerPath     = $this->modulesBasePath . $moduleName . "/src/Handler/";
        $this->cacheFile = APP_ROOT . "/data/cache/routes." . strtolower($moduleName) . ".cache.php";

        if (! is_dir($handlerPath)) {
            throw new RuntimeException("Handler folder not found at: $moduleName/src/");
        }

        $files        = $this->findHandlerFiles($handlerPath);
        $lastModified = $this->getLastModified($files);

        if ($this->canUseOpcache()) {
            if (self::$opcacheRoutes[$moduleName] ?? false) {
                $cached = self::$opcacheRoutes[$moduleName];
                if ($cached['_lastModified'] === $lastModified) {
                    $this->registerFromCache($cached);
                    return;
                }
            }

            if ($this->isCacheValid($lastModified)) {
                $routes                           = include $this->cacheFile;
                self::$opcacheRoutes[$moduleName] = $routes;
                $this->registerFromCache($routes);
                return;
            }
        }

        if (! $this->canUseOpcache() && $this->isCacheValid($lastModified)) {
            $routes = include $this->cacheFile;
            $this->registerFromCache($routes);
            return;
        }

        // If cache does not exist/is not up to date, create a new one
        $routes = $this->generateRoutes($files);
        $this->writeCache($routes, $lastModified);

        if ($this->canUseOpcache()) {
            self::$opcacheRoutes[$moduleName] = [
                '_lastModified' => $lastModified,
                'data'          => $routes,
            ];
        }
    }

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

    private function getLastModified(array $files): int
    {
        $times = [];
        foreach ($files as $file) {
            $times[] = filemtime($file);
        }
        return max($times);
    }

    private function isCacheValid(int $lastModified): bool
    {
        if (! file_exists($this->cacheFile)) {
            return false;
        }
        $data = include $this->cacheFile;
        return isset($data['_lastModified']) && $data['_lastModified'] === $lastModified;
    }

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

                $this->app->route($route->path, $pipeline, $route->methods)
                    ->setOptions(['meta' => $meta]);
            }
        }
        return $routes;
    }

    private function registerFromCache(array $routes): void
    {
        foreach ($routes['data'] as $r) {
            $this->app->route($r['path'], $r['pipeline'], $r['methods'])
                ->setOptions($r['options']);
        }
    }

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

    private function resolveNamespace(string $filePath): ?string
    {
        $basePath = APP_ROOT . '/src/';
        $relative = str_replace([$basePath, '/', '.php'], ['', '\\', ''], $filePath);
        return str_replace('\\src\\', '\\', $relative);
    }

    private function canUseOpcache(): bool
    {
        $status = opcache_get_status(false);
        return is_array($status) && ! empty($status['opcache_enabled']);
    }
}
