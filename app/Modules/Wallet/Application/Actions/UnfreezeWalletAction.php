<?php

namespace App\Modules\Wallet\Application\Actions;

use App\Modules\Wallet\Application\Services\WalletLifecycleService;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;

final class UnfreezeWalletAction
{
    public function __construct(private readonly WalletLifecycleService $lifecycle) {}

    public function execute(Wallet $wallet): Wallet
    {
        return $this->lifecycle->unfreeze($wallet);
    }
}
