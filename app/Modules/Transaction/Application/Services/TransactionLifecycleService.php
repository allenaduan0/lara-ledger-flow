<?php

namespace App\Modules\Transaction\Application\Services;

use App\Modules\Audit\Application\Services\AuditService;
use App\Modules\Transaction\Domain\Enums\TransactionStatus;
use App\Modules\Transaction\Infrastructure\Persistence\Models\FinancialTransaction;
use App\Modules\Transaction\Infrastructure\Persistence\Models\TransactionStatusHistory;
use DomainException;

final class TransactionLifecycleService
{
    public function __construct(private readonly AuditService $audit) {}

    public function recordCreated(FinancialTransaction $transaction): void
    {
        TransactionStatusHistory::query()->create(['transaction_id' => $transaction->id, 'to_status' => TransactionStatus::Created, 'actor_id' => $transaction->initiated_by, 'created_at' => now()]);
        $this->audit->record($transaction->initiated_by, 'transaction.created', FinancialTransaction::class, $transaction->id, null, ['status' => 'created', 'type' => $transaction->type->value]);
    }

    public function transition(FinancialTransaction $transaction, TransactionStatus $next, ?string $reason = null): void
    {
        $current = $transaction->status;
        if (! $current->canTransitionTo($next)) {
            throw new DomainException("Invalid transaction transition from {$current->value} to {$next->value}.");
        }

        $attributes = ['status' => $next];
        if ($next === TransactionStatus::Completed) {
            $attributes['completed_at'] = now();
        }
        if ($next === TransactionStatus::Failed) {
            $attributes['failed_at'] = now();
        }
        if ($next === TransactionStatus::Reversed) {
            $attributes['reversed_at'] = now();
        }
        $transaction->forceFill($attributes)->save();
        TransactionStatusHistory::query()->create(['transaction_id' => $transaction->id, 'from_status' => $current, 'to_status' => $next, 'actor_id' => $transaction->initiated_by, 'reason' => $reason, 'created_at' => now()]);
        $this->audit->record($transaction->initiated_by, 'transaction.status_changed', FinancialTransaction::class, $transaction->id, ['status' => $current->value], ['status' => $next->value], ['reason' => $reason]);
    }
}
