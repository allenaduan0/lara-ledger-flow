<?php

use App\Modules\Ledger\Infrastructure\Providers\LedgerServiceProvider;
use App\Modules\Reconciliation\Infrastructure\Providers\ReconciliationServiceProvider;
use App\Modules\Transaction\Infrastructure\Providers\TransactionEventServiceProvider;
use App\Modules\Transaction\Infrastructure\Providers\TransactionServiceProvider;
use App\Modules\Wallet\Infrastructure\Providers\WalletServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    WalletServiceProvider::class,
    LedgerServiceProvider::class,
    ReconciliationServiceProvider::class,
    TransactionServiceProvider::class,
    TransactionEventServiceProvider::class,
];
