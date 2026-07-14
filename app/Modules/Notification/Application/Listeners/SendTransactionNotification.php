<?php

namespace App\Modules\Notification\Application\Listeners;

use App\Modules\Notification\Infrastructure\Persistence\Models\TransactionNotification;
use App\Modules\Transaction\Domain\Events\TransactionCreated;
use App\Modules\Transaction\Domain\Events\TransferCompleted;
use App\Modules\Transaction\Domain\Events\TransferFailed;
use App\Modules\Transaction\Domain\Events\WalletCredited;
use App\Modules\Transaction\Domain\Events\WalletDebited;
use App\Modules\Transaction\Infrastructure\Persistence\Models\FinancialTransaction;
use App\Modules\Transaction\Infrastructure\Queue\QueuedFinancialListener;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;
use Illuminate\Contracts\Queue\ShouldQueue;

final class SendTransactionNotification implements ShouldQueue
{
    use QueuedFinancialListener;

    public string $queue = 'notifications';

    public function handle(TransactionCreated|TransferCompleted|TransferFailed|WalletCredited|WalletDebited $event): void
    {
        $transaction = FinancialTransaction::query()->findOrFail($event->transactionId);
        $userId = $event instanceof WalletCredited || $event instanceof WalletDebited
            ? Wallet::query()->findOrFail($event->walletId)->user_id
            : $transaction->initiated_by;
        TransactionNotification::query()->firstOrCreate(
            ['event_id' => $event->eventId],
            ['user_id' => $userId, 'transaction_id' => $transaction->id, 'type' => class_basename($event), 'payload' => ['status' => $transaction->status->value, 'amount_minor' => $event->amountMinor, 'currency' => $event->currency], 'delivered_at' => now()],
        );
    }
}
