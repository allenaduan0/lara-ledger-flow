<?php

namespace App\Modules\Reporting\Application\Listeners;

use App\Modules\Reporting\Infrastructure\Persistence\Models\DailyMetric;
use App\Modules\Transaction\Domain\Events\TransactionCreated;
use App\Modules\Transaction\Domain\Events\TransferCompleted;
use App\Modules\Transaction\Domain\Events\TransferFailed;
use App\Modules\Transaction\Infrastructure\Queue\QueuedFinancialListener;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

final class UpdateReportingData implements ShouldQueue
{
    use QueuedFinancialListener;

    public string $queue = 'reporting';

    public function handle(TransactionCreated|TransferCompleted|TransferFailed $event): void
    {
        DB::transaction(function () use ($event): void {
            $inserted = DB::table('processed_reporting_events')->insertOrIgnore(['event_id' => $event->eventId, 'processed_at' => now()]);
            if ($inserted === 0) {
                return;
            }

            $metric = DailyMetric::query()->firstOrCreate(['business_date' => now()->toDateString(), 'currency_code' => $event->currency]);
            if ($event instanceof TransactionCreated) {
                $metric->increment('transactions_created');
            } elseif ($event instanceof TransferCompleted) {
                $metric->increment('transfers_completed');
                $metric->increment('transferred_minor', $event->amountMinor);
            } else {
                $metric->increment('transfers_failed');
            }
        }, attempts: 3);
    }
}
