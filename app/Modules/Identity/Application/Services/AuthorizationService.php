<?php

namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Domain\Contracts\AuthorizableUser;

final class AuthorizationService
{
    public function allows(AuthorizableUser $user, string $permission): bool
    {
        return $user->hasPermission($permission);
    }
}
