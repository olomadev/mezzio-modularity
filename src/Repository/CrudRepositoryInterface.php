<?php

declare(strict_types=1);

namespace Modularity\Repository;

interface CrudRepositoryInterface
{
    public function createEntity(object $entity);

    public function updateEntity(object $entity);

    public function deleteEntity(object $entity);
}
