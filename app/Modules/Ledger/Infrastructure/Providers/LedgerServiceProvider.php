<?php

namespace App\Modules\Ledger\Infrastructure\Providers;

use App\Modules\Ledger\Infrastructure\Wallet\LedgerWalletAccountProvisioner;
use App\Modules\Ledger\Infrastructure\Wallet\LedgerWalletBalanceReader;
use App\Modules\Wallet\Domain\Contracts\WalletAccountProvisioner;
use App\Modules\Wallet\Domain\Contracts\WalletBalanceReader;
use Illuminate\Support\ServiceProvider;

class LedgerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WalletAccountProvisioner::class, LedgerWalletAccountProvisioner::class);
        $this->app->bind(WalletBalanceReader::class, LedgerWalletBalanceReader::class);
    }
}
