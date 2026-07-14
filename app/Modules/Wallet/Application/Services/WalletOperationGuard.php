<?php

namespace App\Modules\Wallet\Application\Services;

use App\Modules\Wallet\Domain\Enums\WalletStatus;
use App\Modules\Wallet\Domain\Exceptions\FrozenWalletException;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;

final class WalletOperationGuard
{
    public function ensureCanOperate(Wallet $wallet): void
    {
        if ($wallet->status !== WalletStatus::Active) {
            throw new FrozenWalletException;
        }
    }
}
