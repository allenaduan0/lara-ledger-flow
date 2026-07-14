<?php

namespace App\Modules\Wallet\Domain\Contracts;

use App\Modules\Wallet\Domain\Data\WalletBalance;

interface WalletBalanceReader
{
    public function forWallet(string $walletId, string $currencyCode): WalletBalance;
}
