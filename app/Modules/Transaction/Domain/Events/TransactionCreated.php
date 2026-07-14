<?php

namespace App\Modules\Transaction\Domain\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TransactionCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public string $eventId;

    public function __construct(
        public readonly string $transactionId,
        public readonly int $userId,
        public readonly string $type,
        public readonly int $amountMinor,
        public readonly string $currency,
    ) {
        $this->eventId = "transaction:{$transactionId}:created";
    }
}
