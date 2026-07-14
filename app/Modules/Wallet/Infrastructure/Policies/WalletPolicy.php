<?php

namespace App\Modules\Wallet\Infrastructure\Policies;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;

final class WalletPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Wallet $wallet): bool
    {
        return $wallet->user_id === $user->getKey();
    }

    public function freeze(User $user, Wallet $wallet): bool
    {
        return $this->view($user, $wallet);
    }

    public function unfreeze(User $user, Wallet $wallet): bool
    {
        return $this->view($user, $wallet);
    }
}
