<?php

namespace App\Modules\Wallet\Application\Actions;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Wallet\Domain\Contracts\WalletAccountProvisioner;
use App\Modules\Wallet\Domain\Enums\WalletStatus;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;
use Illuminate\Support\Facades\DB;

final class CreateWalletAction
{
    public function __construct(private readonly WalletAccountProvisioner $accounts) {}

    public function execute(User $user, string $currencyCode, ?string $name = null): Wallet
    {
        return DB::transaction(function () use ($user, $currencyCode, $name): Wallet {
            $wallet = Wallet::query()->create([
                'user_id' => $user->getKey(),
                'currency_code' => strtoupper($currencyCode),
                'name' => $name,
                'status' => WalletStatus::Active,
            ]);
            $wallet->limit()->create();
            $this->accounts->provision($wallet->id, $wallet->currency_code);

            return $wallet->load(['currency', 'limit']);
        });
    }
}
