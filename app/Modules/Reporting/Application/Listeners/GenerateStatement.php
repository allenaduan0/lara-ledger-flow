<?php

namespace App\Modules\Reporting\Application\Listeners;

use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\Reporting\Infrastructure\Persistence\Models\StatementEntry;
use App\Modules\Transaction\Domain\Events\WalletCredited;
use App\Modules\Transaction\Domain\Events\WalletDebited;
use App\Modules\Transaction\Infrastructure\Persistence\Models\FinancialTransaction;
use App\Modules\Transaction\Infrastructure\Queue\QueuedFinancialListener;
use Illuminate\Contracts\Queue\ShouldQueue;

final class GenerateStatement implements ShouldQueue
{
    use QueuedFinancialListener;

    public string $queue = 'statements';

    public function handle(WalletCredited|WalletDebited $event): void
    {
        $transaction = FinancialTransaction::query()->findOrFail($event->transactionId);
        StatementEntry::query()->firstOrCreate(
            ['event_id' => $event->eventId],
            ['wallet_id' => $event->walletId, 'transaction_id' => $event->transactionId, 'ledger_entry_id' => $event->ledgerEntryId, 'direction' => $event instanceof WalletCredited ? EntryDirection::Credit : EntryDirection::Debit, 'amount_minor' => $event->amountMinor, 'currency_code' => $event->currency, 'occurred_at' => $transaction->completed_at ?? $transaction->created_at],
        );
    }
}
