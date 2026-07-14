<?php

namespace App\Modules\Ledger\Infrastructure\Wallet;

use App\Modules\Ledger\Application\Services\BalanceCalculationService;
use App\Modules\Ledger\Infrastructure\Persistence\Models\Account;
use App\Modules\Wallet\Domain\Contracts\WalletBalanceReader;
use App\Modules\Wallet\Domain\Data\WalletBalance;

final class LedgerWalletBalanceReader implements WalletBalanceReader
{
    public function __construct(private readonly BalanceCalculationService $balances) {}

    public function forWallet(string $walletId, string $currencyCode): WalletBalance
    {
        $account = Account::query()->where('wallet_id', $walletId)->first();
        if (! $account) {
            return new WalletBalance(null, null, $currencyCode);
        }

        $balance = $this->balances->forAccount($account);

        return new WalletBalance($balance->balanceMinor, $balance->balanceMinor, $balance->currency);
    }
}
