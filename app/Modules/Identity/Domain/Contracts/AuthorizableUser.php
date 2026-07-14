<?php

namespace App\Modules\Identity\Domain\Contracts;

interface AuthorizableUser
{
    public function hasRole(string $role): bool;

    public function hasPermission(string $permission): bool;
}
