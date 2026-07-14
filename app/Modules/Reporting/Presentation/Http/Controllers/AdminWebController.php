<?php

namespace App\Modules\Reporting\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ledger\Infrastructure\Persistence\Models\LedgerTransaction;
use App\Modules\Reconciliation\Application\Actions\RunDailyReconciliationAction;
use App\Modules\Reconciliation\Infrastructure\Persistence\Models\ReconciliationReport;
use App\Modules\Reporting\Application\Services\LedgerExplorerService;
use App\Modules\Reporting\Application\Services\OperationsDashboardService;
use App\Modules\Reporting\Application\Services\TransactionMonitoringService;
use App\Modules\Transaction\Infrastructure\Persistence\Models\FinancialTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminWebController extends Controller
{
    public function dashboard(OperationsDashboardService $dashboard): View
    {
        return view('admin.dashboard', $dashboard->summary());
    }

    public function transactions(Request $request, TransactionMonitoringService $monitor): View
    {
        return view('admin.transactions.index', ['transactions' => $monitor->search($request->only(['q', 'status', 'type', 'currency', 'date_from', 'date_to']))]);
    }

    public function transaction(FinancialTransaction $transaction, TransactionMonitoringService $monitor): View
    {
        return view('admin.transactions.show', ['transaction' => $monitor->details($transaction)]);
    }

    public function ledger(LedgerTransaction $ledgerTransaction, LedgerExplorerService $explorer): View
    {
        return view('admin.ledger.show', $explorer->inspect($ledgerTransaction));
    }

    public function reconciliationReports(): View
    {
        return view('admin.reconciliation.index', ['reports' => ReconciliationReport::query()->withCount('discrepancies')->latest('business_date')->paginate(25)]);
    }

    public function reconciliationReport(ReconciliationReport $report): View
    {
        return view('admin.reconciliation.show', ['report' => $report->load('discrepancies')]);
    }

    public function runReconciliation(Request $request, RunDailyReconciliationAction $action): RedirectResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']]);
        $action->execute($data['date'], $request->user()->getKey());

        return back()->with('status', 'Reconciliation completed.');
    }
}
