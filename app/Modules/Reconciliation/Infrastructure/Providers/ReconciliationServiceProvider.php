<?php

namespace App\Modules\Reconciliation\Infrastructure\Providers;

use App\Modules\Reconciliation\Presentation\Console\RunDailyReconciliation;
use Illuminate\Support\ServiceProvider;

class ReconciliationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([RunDailyReconciliation::class]);
        }
    }
}
