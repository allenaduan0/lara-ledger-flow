<?php

namespace App\Modules\Ledger\Infrastructure\Wallet;

use App\Modules\Ledger\Domain\Enums\AccountType;
use App\Modules\Ledger\Infrastructure\Persistence\Models\Account;
use App\Modules\Wallet\Domain\Contracts\WalletAccountProvisioner;

final class LedgerWalletAccountProvisioner implements WalletAccountProvisioner
{
    public function provision(string $walletId, string $currencyCode): void
    {
        $type = AccountType::Liability;
        Account::query()->create([
            'wallet_id' => $walletId,
            'code' => "wallet:{$walletId}",
            'name' => "Wallet {$walletId}",
            'type' => $type,
            'normal_balance' => $type->normalBalance(),
            'currency_code' => $currencyCode,
            'status' => 'active',
        ]);
    }
}
