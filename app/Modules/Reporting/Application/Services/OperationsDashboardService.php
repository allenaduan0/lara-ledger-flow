<?php

namespace App\Modules\Reporting\Application\Services;

use App\Modules\Ledger\Infrastructure\Persistence\Models\LedgerEntry;
use App\Modules\Reconciliation\Infrastructure\Persistence\Models\ReconciliationReport;
use App\Modules\Transaction\Infrastructure\Persistence\Models\FinancialTransaction;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;

final class OperationsDashboardService
{
    public function summary(): array
    {
        return [
            'wallets' => Wallet::query()->count(),
            'transactions_today' => FinancialTransaction::query()->whereDate('created_at', today())->count(),
            'processing_transactions' => FinancialTransaction::query()->whereIn('status', ['created', 'pending', 'processing'])->count(),
            'failed_transactions' => FinancialTransaction::query()->where('status', 'failed')->count(),
            'ledger_entries' => LedgerEntry::query()->count(),
            'open_reconciliation_breaks' => ReconciliationReport::query()->where('status', 'discrepancy')->count(),
            'recent_transactions' => FinancialTransaction::query()->with(['currency', 'initiator:id,name,email'])->latest()->limit(10)->get(),
            'recent_reports' => ReconciliationReport::query()->latest('business_date')->limit(5)->get(),
        ];
    }
}
