<?php

namespace App\Modules\Reconciliation\Application\Services;

use App\Modules\Audit\Application\Services\AuditService;
use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\Ledger\Infrastructure\Persistence\Models\LedgerTransaction;
use App\Modules\Reconciliation\Infrastructure\Persistence\Models\ReconciliationReport;
use App\Modules\Transaction\Infrastructure\Persistence\Models\FinancialTransaction;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Currency;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class DailyReconciliationService
{
    public function __construct(private readonly AuditService $audit) {}

    /** @return Collection<int, ReconciliationReport> */
    public function run(string $date, ?int $actorId = null): Collection
    {
        $businessDate = CarbonImmutable::parse($date, 'UTC')->startOfDay();
        $end = $businessDate->addDay();

        return DB::transaction(function () use ($businessDate, $end, $actorId): Collection {
            $journals = LedgerTransaction::query()->with('entries.account')
                ->where('posted_at', '>=', $businessDate)->where('posted_at', '<', $end)->get();
            $missing = FinancialTransaction::query()->whereIn('status', ['completed', 'reversed'])
                ->whereNull('ledger_transaction_id')->where('completed_at', '>=', $businessDate)->where('completed_at', '<', $end)->get();
            $currencyCodes = Currency::query()->pluck('code')->merge(
                $journals->flatMap(fn ($journal) => $journal->entries->pluck('account.currency_code')),
            )->merge($missing->pluck('currency_code'))->unique()->sort()->values();

            return $currencyCodes->map(function (string $currency) use ($businessDate, $journals, $missing, $actorId): ReconciliationReport {
                $debits = 0;
                $credits = 0;
                $invalid = [];
                $journalCount = 0;

                foreach ($journals as $journal) {
                    $entries = $journal->entries->filter(fn ($entry) => $entry->account->currency_code === $currency);
                    if ($entries->isEmpty()) {
                        continue;
                    }
                    $journalCount++;
                    $journalDebits = (int) $entries->where('direction', EntryDirection::Debit)->sum('amount_minor');
                    $journalCredits = (int) $entries->where('direction', EntryDirection::Credit)->sum('amount_minor');
                    $debits += $journalDebits;
                    $credits += $journalCredits;
                    if ($journalDebits !== $journalCredits) {
                        $invalid[] = ['reference' => $journal->reference, 'expected' => $journalDebits, 'actual' => $journalCredits];
                    }
                }

                $missingForCurrency = $missing->where('currency_code', $currency);
                $difference = $credits - $debits;
                $status = $difference === 0 && $invalid === [] && $missingForCurrency->isEmpty() ? 'balanced' : 'discrepancy';
                $report = ReconciliationReport::query()->updateOrCreate(
                    ['business_date' => $businessDate->toDateString(), 'currency_code' => $currency],
                    ['expected_minor' => $debits, 'ledger_minor' => $credits, 'difference_minor' => $difference, 'journal_count' => $journalCount, 'invalid_journal_count' => count($invalid), 'missing_journal_count' => $missingForCurrency->count(), 'status' => $status, 'generated_by' => $actorId, 'generated_at' => now()],
                );
                $report->discrepancies()->delete();

                foreach ($invalid as $item) {
                    $report->discrepancies()->create(['type' => 'unbalanced_journal', 'reference' => $item['reference'], 'expected_minor' => $item['expected'], 'actual_minor' => $item['actual'], 'difference_minor' => $item['actual'] - $item['expected'], 'details' => ['currency' => $currency], 'created_at' => now()]);
                }
                foreach ($missingForCurrency as $transaction) {
                    $report->discrepancies()->create(['type' => 'missing_journal', 'reference' => $transaction->reference, 'expected_minor' => $transaction->amount_minor, 'actual_minor' => 0, 'difference_minor' => -$transaction->amount_minor, 'details' => ['transaction_id' => $transaction->id], 'created_at' => now()]);
                }

                $this->audit->record($actorId, 'reconciliation.generated', ReconciliationReport::class, $report->id, null, ['status' => $status, 'difference_minor' => $difference], ['business_date' => $businessDate->toDateString(), 'currency' => $currency]);

                return $report->load('discrepancies');
            });
        }, attempts: 3);
    }
}
