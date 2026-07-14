<?php

namespace App\Modules\Wallet\Infrastructure\Providers;

use App\Modules\Wallet\Domain\Contracts\WalletBalanceReader;
use App\Modules\Wallet\Infrastructure\Ledger\UnavailableWalletBalanceReader;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;
use App\Modules\Wallet\Infrastructure\Policies\WalletPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class WalletServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WalletBalanceReader::class, UnavailableWalletBalanceReader::class);
    }

    public function boot(): void
    {
        Gate::policy(Wallet::class, WalletPolicy::class);
    }
}
