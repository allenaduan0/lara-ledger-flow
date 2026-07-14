<?php

namespace App\Modules\Transaction\Domain\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class WalletDebited implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public string $eventId;

    public function __construct(
        public readonly string $transactionId,
        public readonly string $walletId,
        public readonly string $ledgerEntryId,
        public readonly int $amountMinor,
        public readonly string $currency,
    ) {
        $this->eventId = "transaction:{$transactionId}:entry:{$ledgerEntryId}:debited";
    }
}
