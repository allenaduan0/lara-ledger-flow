<?php

namespace App\Modules\Wallet\Application\Services;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Wallet\Domain\Enums\WalletStatus;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;
use Illuminate\Support\Facades\DB;

final class WalletLifecycleService
{
    public function freeze(Wallet $wallet, User $actor, ?string $reason): Wallet
    {
        return DB::transaction(function () use ($wallet, $actor, $reason): Wallet {
            $locked = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
            $locked->forceFill([
                'status' => WalletStatus::Frozen,
                'frozen_at' => now(),
                'frozen_by' => $actor->getKey(),
                'freeze_reason' => $reason,
            ])->save();

            return $locked->refresh();
        });
    }

    public function unfreeze(Wallet $wallet): Wallet
    {
        return DB::transaction(function () use ($wallet): Wallet {
            $locked = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
            $locked->forceFill([
                'status' => WalletStatus::Active,
                'frozen_at' => null,
                'frozen_by' => null,
                'freeze_reason' => null,
            ])->save();

            return $locked->refresh();
        });
    }
}
