<?php

declare(strict_types=1);

namespace Modularity\Authorization;

interface PermissionRepositoryInterface
{
    /**
     * Find grouped permissions by role
     */
    public function findGroupedByRole(): array;
}
