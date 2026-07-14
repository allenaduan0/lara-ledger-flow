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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminApiController extends Controller
{
    public function dashboard(OperationsDashboardService $dashboard): JsonResponse
    {
        return response()->json(['data' => $dashboard->summary()]);
    }

    public function transactions(Request $request, TransactionMonitoringService $monitor): JsonResponse
    {
        return response()->json($monitor->search($request->only(['q', 'status', 'type', 'currency', 'date_from', 'date_to']), (int) $request->input('per_page', 25)));
    }

    public function transaction(FinancialTransaction $transaction, TransactionMonitoringService $monitor): JsonResponse
    {
        return response()->json(['data' => $monitor->details($transaction)]);
    }

    public function ledger(LedgerTransaction $ledgerTransaction, LedgerExplorerService $explorer): JsonResponse
    {
        return response()->json(['data' => $explorer->inspect($ledgerTransaction)]);
    }

    public function reconciliationReports(): JsonResponse
    {
        return response()->json(ReconciliationReport::query()->withCount('discrepancies')->latest('business_date')->paginate(25));
    }

    public function reconciliationReport(ReconciliationReport $report): JsonResponse
    {
        return response()->json(['data' => $report->load('discrepancies')]);
    }

    public function runReconciliation(Request $request, RunDailyReconciliationAction $action): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']]);

        return response()->json(['data' => $action->execute($data['date'], $request->user()->getKey())], 201);
    }
}
