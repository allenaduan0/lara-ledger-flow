<?php

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Authorization\RoleName;
use App\Modules\Identity\Infrastructure\Persistence\Models\Role;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Facades\DB;

final class RegisterUserAction
{
    public function execute(string $name, string $email, string $password): User
    {
        return DB::transaction(function () use ($name, $email, $password): User {
            $user = User::query()->create(compact('name', 'email', 'password'));
            $user->roles()->attach(Role::query()->where('name', RoleName::Customer->value)->firstOrFail());

            return $user->load('roles.permissions');
        });
    }
}
