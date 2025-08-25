<?php

declare(strict_types=1);

namespace Modularity\Db;

use Laminas\Db\Sql\Expression;
use RuntimeException;

use function implode;
use function strtolower;

final class JsonExpressionHelper
{
    private string $dbPlatform;

    public function __construct(string $dbPlatform = 'mysql')
    {
        $this->dbPlatform = strtolower($dbPlatform);
    }

    public function jsonObject(array $map): Expression
    {
        $pairs = [];
        foreach ($map as $key => $value) {
            $pairs[] = "'" . $key . "', " . $value;
        }

        if ($this->dbPlatform === 'mysql') {
            return new Expression("JSON_OBJECT(" . implode(", ", $pairs) . ")");
        }

        if ($this->dbPlatform === 'pgsql') {
            return new Expression("json_build_object(" . implode(", ", $pairs) . ")");
        }

        throw new RuntimeException("Unsupported DB platform: " . $this->dbPlatform);
    }

    public function jsonArrayAgg(string $column): Expression
    {
        if ($this->dbPlatform === 'mysql') {
            return new Expression("JSON_ARRAYAGG($column)");
        }

        if ($this->dbPlatform === 'pgsql') {
            return new Expression("json_agg($column)");
        }

        throw new RuntimeException("Unsupported DB platform: " . $this->dbPlatform);
    }

    public function ucfirst(string $column): string
    {
        if ($this->dbPlatform === 'mysql') {
            return "CONCAT(UPPER(SUBSTRING($column, 1, 1)), LOWER(SUBSTRING($column, 2)))";
        }

        if ($this->dbPlatform === 'pgsql') {
            return "UPPER(SUBSTRING($column FROM 1 FOR 1)) || LOWER(SUBSTRING($column FROM 2))";
        }

        throw new RuntimeException("Unsupported DB platform: " . $this->dbPlatform);
    }

    public function upper(string $column): string
    {
        return "UPPER($column)";
    }

    public function lower(string $column): string
    {
        return "LOWER($column)";
    }
}
