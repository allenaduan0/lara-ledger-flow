<?php

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;

final class IssueAccessTokenAction
{
    public function execute(User $user, string $deviceName): string
    {
        return $user->createToken($deviceName)->plainTextToken;
    }
}
