<?php

namespace App\Modules\Wallet\Application\Actions;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Wallet\Application\Services\WalletLifecycleService;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;

final class FreezeWalletAction
{
    public function __construct(private readonly WalletLifecycleService $lifecycle) {}

    public function execute(Wallet $wallet, User $actor, ?string $reason): Wallet
    {
        return $this->lifecycle->freeze($wallet, $actor, $reason);
    }
}
