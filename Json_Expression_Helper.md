
# Json Expression Helper for PostgreSQL and MySQL

Object

```php
$select->columns([
    'id',
    'action' => $jsonHelper->jsonObject([
        'id' => 'p.action',
        'name' => $jsonHelper->ucfirst('p.action')
    ]),
    'method' => $jsonHelper->jsonObject([
        'id' => 'p.method',
        'name' => $jsonHelper->upper('p.method')
    ]),
]);
```


```php
$platform = strtolower($adapter->getPlatform()->getName());
$jsonHelper = new \Olobase\Db\JsonExpressionHelper($platform);

$select->columns([
    'id',
    'actions' => $jsonHelper->jsonArrayAgg(
        $jsonHelper->jsonObject([
            'id'   => 'p.action',
            'name' => $jsonHelper->ucfirst('p.action')
        ])
    ),
    'methods' => $jsonHelper->jsonArrayAgg(
        $jsonHelper->jsonObject([
            'id'   => 'p.method',
            'name' => $jsonHelper->upper('p.method')
        ])
    ),
    'module',
    'name',
    'route',
]);
```


## Nested Json with JSon Array Agg

```php
$platform = strtolower($adapter->getPlatform()->getName());
$jsonHelper = new \Authorization\Db\JsonExpressionHelper($platform);

$select->columns([
    'permissions' => $jsonHelper->jsonArrayAgg(
        $jsonHelper->jsonObject([
            'uuid'      => 'UuidFromBin(p.uuid)',
            'name'      => 'p.name',
            'shortName' => 'p.short_name',
            'description' => 'p.description'
        ])
    )
]);
```
