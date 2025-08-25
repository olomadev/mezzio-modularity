<?php

declare(strict_types=1);

namespace Modularity\Middleware;

use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\InputFilter\InputFilterPluginManager;
use Mezzio\Router\RouteResult;
use Modularity\Attribute\Entity;
use Modularity\Filter\AttributeInputFilterCollector;
use Modularity\Mapper\InputSchemaMapper;
use Modularity\Validation\ValidationErrorFormatterInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;

use function get_object_vars;
use function in_array;
use function is_a;

class EntityMiddleware implements MiddlewareInterface
{
    private static array $cache = [];

    public function __construct(
        private InputFilterPluginManager $filterManager,
        private ValidationErrorFormatterInterface $errorFormatter,
        private AdapterInterface $adapter
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routeResult  = $request->getAttribute(RouteResult::class);
        $matchedRoute = $routeResult?->getMatchedRoute();

        if (! $matchedRoute) {
            return $handler->handle($request);
        }
        $middlewarePipe = $matchedRoute->getMiddleware();

        $ref  = new ReflectionClass($middlewarePipe);
        $prop = $ref->getProperty('pipeline');
        $prop->setAccessible(true);

        $queue = $prop->getValue($middlewarePipe);

        foreach ($queue as $entry) {
            $vars = get_object_vars($entry);
            $name = $vars['middlewareName'] ?? null;

            if ($name === null || ! is_a($name, RequestHandlerInterface::class, true)) {
                continue;
            }

            // op cache
            if (! isset(self::$cache[$name])) {
                $ref = new ReflectionClass($name);
                if ($ref->hasMethod('handle')) {
                    $method             = $ref->getMethod('handle');
                    $attributes         = $method->getAttributes(Entity::class);
                    self::$cache[$name] = $attributes
                        ? $attributes[0]->newInstance()
                        : null;
                } else {
                    self::$cache[$name] = null;
                }
            }

            $entityAttribute = self::$cache[$name];
            if ($entityAttribute instanceof Entity) {
                if (in_array($request->getMethod(), ['POST', 'PUT', 'OPTIONS'], true)) {
                    $inputData = $request->getParsedBody();
                } else {
                    $inputData = $request->getQueryParams();
                }
                if (isset($entityAttribute->dto) && $entityAttribute->dto) {
                    $dto       = new ($entityAttribute->dto)();
                    $collector = new AttributeInputFilterCollector($this->filterManager, $this->adapter);
                    $filter    = $collector->fromObject($dto, $inputData);
                    if (! $filter->isValid()) {
                        return new JsonResponse($this->errorFormatter->format($filter), 400);
                    }
                    $mapper = new InputSchemaMapper();
                    $entity = $mapper->mapToEntity($filter, $dto, $entityAttribute->entity);
                } else {
                    $mapper = new InputSchemaMapper();
                    $entity = $mapper->mapToEntity($filter, $entityAttribute->entity);
                }
                $request = $request->withAttribute('entity', $entity);
            }
        }

        return $handler->handle($request);
    }
}
