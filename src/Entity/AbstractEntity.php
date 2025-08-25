<?php

declare(strict_types=1);

namespace Modularity\Entity;

use Modularity\Util\RandomStringHelper;
use ReflectionClass;

use function get_class_methods;
use function in_array;
use function is_string;
use function json_decode;
use function json_last_error;
use function lcfirst;
use function preg_replace;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function substr;
use function ucfirst;
use function ucwords;

use const JSON_ERROR_NONE;
use const JSON_THROW_ON_ERROR;

abstract class AbstractEntity
{
    /** @var string|int|null */
    protected $id;

    public function __construct(string|int|null $id = null)
    {
        $this->id = $id;

        if ($id === null) {
            $this->id = RandomStringHelper::generateUuid();
        }
    }

    public function getId(): string|int
    {
        return $this->id;
    }

    public function toCamelCaseArray(array $exclude = []): array
    {
        $data = [];
        foreach (get_class_methods($this) as $method) {
            if (str_starts_with($method, 'get')) {
                $propName = lcfirst(substr($method, 3)); // getName -> name
                if (in_array($propName, $exclude, true)) {
                    continue;
                }
                $data[self::snakeToCamel($propName)] = $this->{$method}();
            }
        }
        return $data;
    }

    public function toSnakeCaseArray(array $exclude = []): array
    {
        $data = [];
        foreach (get_class_methods($this) as $method) {
            if (str_starts_with($method, 'get')) {
                $propName = lcfirst(substr($method, 3)); // getName -> name
                if (in_array($propName, $exclude, true)) {
                    continue;
                }
                $data[self::camelToSnake($propName)] = $this->{$method}();
            }
        }
        return $data;
    }

    public static function hydrate(array $row, ?string $entityClass = null): static
    {
        $entityClass ??= static::class;
        $reflection    = new ReflectionClass($entityClass);

        // 1. Create the entity with minimal constructor arguments
        $constructorParams = [];
        foreach ($reflection->getConstructor()->getParameters() as $param) {
            $constructorParams[] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
        }
        /** @var static $entity */
        $entity = $reflection->newInstanceArgs($constructorParams);

        // 2. Set properties using setter or directly via property
        foreach ($reflection->getConstructor()->getParameters() as $param) {
            $name  = $param->getName();
            $value = $row[$name] ?? ($param->isDefaultValueAvailable() ? $param->getDefaultValue() : null);

            $type  = $param->getType()?->getName();
            $value = self::castValue($value, $type);

            $setter = 'set' . ucfirst($name);
            if ($reflection->hasMethod($setter)) {
                $entity->{$setter}($value);
            } elseif ($reflection->hasProperty($name)) {
                $prop = $reflection->getProperty($name);
                $prop->setAccessible(true);
                $prop->setValue($entity, $value);
            }
        }

        return $entity;
    }

    private static function castValue(mixed $value, ?string $type): mixed
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value) && self::isJson($value)) {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        }
        return match ($type) {
            'string' => (string) $value,
            'integer', 'int' => (int) $value,
            'number', 'float' => (float) $value,
            'boolean', 'bool' => is_string($value) ? in_array(strtolower($value), ['1', 'true', 'yes'], true) : (bool) $value,
            default  => $value,
        };
    }

    private static function snakeToCamel(string $string): string
    {
        return lcfirst(str_replace('_', '', ucwords($string, '_')));
    }

    private static function camelToSnake(string $input): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($input)));
    }

    private static function isJson(string $string): bool
    {
        if ($string === '' || ! is_string($string)) {
            return false;
        }

        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
