<?php

namespace App\Modules\Wallet\Infrastructure\Ledger;

use App\Modules\Wallet\Domain\Contracts\WalletBalanceReader;
use App\Modules\Wallet\Domain\Data\WalletBalance;

final class UnavailableWalletBalanceReader implements WalletBalanceReader
{
    public function forWallet(string $walletId, string $currencyCode): WalletBalance
    {
        return new WalletBalance(null, null, $currencyCode);
    }
}
