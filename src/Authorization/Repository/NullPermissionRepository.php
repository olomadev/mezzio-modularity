<?php

declare(strict_types=1);

namespace Modularity\Authorization\Repository;

use Modularity\Authorization\Contract\PermissionRepositoryInterface;

class NullPermissionRepository implements PermissionRepositoryInterface
{
    /**
     * Find permissions
     */
    public function findGroupedByRole(): array
    {
        return [
            'admin' => [],
            'user'  => [],
        ];
    }
}
