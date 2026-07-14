<?php

namespace App\Modules\Transaction\Infrastructure\Providers;

use App\Modules\Transaction\Infrastructure\Persistence\Models\FinancialTransaction;
use App\Modules\Transaction\Infrastructure\Policies\TransactionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class TransactionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(FinancialTransaction::class, TransactionPolicy::class);
    }
}
