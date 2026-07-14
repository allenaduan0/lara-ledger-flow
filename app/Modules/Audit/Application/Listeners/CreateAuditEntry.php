<?php

namespace App\Modules\Audit\Application\Listeners;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Modules\Transaction\Domain\Events\TransactionCreated;
use App\Modules\Transaction\Domain\Events\TransferCompleted;
use App\Modules\Transaction\Domain\Events\TransferFailed;
use App\Modules\Transaction\Domain\Events\WalletCredited;
use App\Modules\Transaction\Domain\Events\WalletDebited;
use App\Modules\Transaction\Infrastructure\Queue\QueuedFinancialListener;
use Illuminate\Contracts\Queue\ShouldQueue;

final class CreateAuditEntry implements ShouldQueue
{
    use QueuedFinancialListener;

    public string $queue = 'audit';

    public function handle(TransactionCreated|TransferCompleted|TransferFailed|WalletCredited|WalletDebited $event): void
    {
        AuditLog::query()->firstOrCreate(
            ['event_id' => $event->eventId],
            ['actor_id' => $event->userId ?? null, 'action' => 'domain_event.'.class_basename($event), 'subject_type' => 'financial_transaction', 'subject_id' => $event->transactionId, 'after_state' => ['event' => class_basename($event)], 'metadata' => ['amount_minor' => $event->amountMinor, 'currency' => $event->currency], 'created_at' => now()],
        );
    }
}
