<?php

declare(strict_types=1);

namespace Modularity\Dto;

use OpenApi\Attributes as OA;
use ReflectionClass;
use ReflectionProperty;

use function array_map;
use function class_exists;
use function end;
use function explode;
use function in_array;
use function is_array;
use function is_object;
use function is_string;
use function json_decode;
use function json_last_error;
use function lcfirst;
use function method_exists;
use function preg_replace;
use function str_replace;
use function strtolower;
use function ucwords;

use const JSON_ERROR_NONE;
use const JSON_THROW_ON_ERROR;

abstract class AbstractDto
{
    public function toArray(): array
    {
        $result     = [];
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();

            if (! $property->isInitialized($this)) {
                $result[$name] = null;
                continue;
            }

            $value = $property->getValue($this);

            if ($value instanceof self) {
                // DTO ise kendi toArray() çağır
                $result[$name] = $value->toArray();
            } elseif (is_array($value)) {
                // Array ise map et
                $result[$name] = array_map(function ($v) {
                    if ($v instanceof self) {
                        return $v->toArray();
                    }
                    if (is_object($v) && method_exists($v, 'toCamelCaseArray')) {
                        return $v->toCamelCaseArray();
                    }
                    return $v;
                }, $value);
            } elseif (is_object($value) && method_exists($value, 'toCamelCaseArray')) {
                // Tek bir entity nesnesi
                $result[$name] = $value->toCamelCaseArray();
            } else {
                // Normal değer
                $result[$name] = $value;
            }
        }

        return $result;
    }

    public static function hydrate(array $row, $key = 'data'): static
    {
        // var_dump($row);

        $dtoClass   = static::class;
        $reflection = new ReflectionClass($dtoClass);

        /** @var static $dto */
        $dto = $reflection->newInstanceWithoutConstructor();

        $debug = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $attributes = $property->getAttributes(OA\Property::class);

            if (empty($attributes)) {
                continue; // Attribute yoksa atla
            }

            foreach ($attributes as $attribute) {
                /** @var OA\Property $oaProperty */
                $oaProperty = $attribute->newInstance();
                $propName   = self::camelToSnake($oaProperty->property ?? $property->getName());

                $debug[] = $propName;

                // // --- ARRAY OBJECT TYPE ---
                // if ($oaProperty->type === 'array' && $oaProperty->items && $oaProperty->items->type === 'object') {
                //     foreach ($oaProperty->items as $itemData) {
                //         if (is_array($itemData) && isset($itemData[0]->property)) {
                //             $$key = []; // current value if present, empty array if not
                //             foreach ($itemData as $itemKey => $item) {
                //                 $column        = self::camelToSnake($item->property);
                //                 $itemRowValue  = $row[$column];
                //                 $$key[$column] = self::castValue($itemRowValue, $item->type);
                //             }
                //             $property->setValue($dto, $$key);
                //         }
                //     }
                // }

                // --- ARRAY OBJECT TYPE ---
                if ($oaProperty->type === 'array' && $oaProperty->items && $oaProperty->items->ref) {
                    if (! isset($row[$propName]) || ! is_array($row[$propName])) {
                        continue;
                    }

                    // Ref'ten hangi DTO kullanılacağını bul
                    $ref      = $oaProperty->items->ref; // "#/components/schemas/PermissionDto"
                    $refClass = null;

                    if ($ref) {
                        $parts      = explode('/', $ref);
                        $schemaName = end($parts); // "PermissionDto"
                        $refClass   = $reflection->getNamespaceName() . "\\" . $schemaName;
                    }

                    $items = [];
                    foreach ($row[$propName] as $itemValue) {
                        if ($refClass && class_exists($refClass)) {
                            if (is_object($itemValue) && method_exists($itemValue, 'toCamelCaseArray')) {
                                $items[] = $refClass::hydrate($itemValue->toCamelCaseArray());
                            } elseif (is_array($itemValue)) {
                                $items[] = $refClass::hydrate($itemValue);
                            }
                        } else {
                            $items[] = $itemValue;
                        }
                    }

                    $property->setValue($dto, $items);
                }
                // --- OBJECT TYPE ---
                elseif ($oaProperty->type === 'object') {
                    if (! isset($row[$propName])) {
                        continue;
                    }

                    $value = $row[$propName];

                    if (is_string($value)) {
                        $value = json_decode($value, true);
                    }

                    if (! is_array($value)) {
                        continue;
                    }

                    $objectData = [];
                    foreach ($oaProperty->properties as $subProperty) {
                        /** @var OA\Property $subProperty */
                        $subPropName  = self::camelToSnake($subProperty->property ?? '');
                        $subPropValue = $value[$subPropName] ?? null;

                        if ($subPropValue !== null) {
                            $objectData[$subPropName] = self::castValue($subPropValue, $subProperty->type);
                        }
                    }

                    $property->setValue($dto, $objectData);
                }
                // --- SCALAR TYPE ---
                elseif (! empty($row[$propName])) {
                    $property->setValue($dto, self::castValue($row[$propName], $oaProperty->type));
                }
            }
        } // end foreach

        // print_r($debug);
        // die;
        return $dto;
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
            'array'    => (array) $value,
            'object'   => (object) $value,
            'string' => (string) $value,
            'integer', 'int' => (int) $value,
            'number', 'float' => (float) $value,
            'boolean', 'bool' => is_string($value) ? in_array(strtolower($value), ['1', 'true', 'yes'], true) : (bool) $value,
            default  => $value,
        };
    }

    private static function isJson(string $string): bool
    {
        if ($string === '' || ! is_string($string)) {
            return false;
        }

        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private static function snakeToCamel(string $string): string
    {
        return lcfirst(str_replace('_', '', ucwords($string, '_')));
    }

    private static function camelToSnake(string $input): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($input)));
    }
}
