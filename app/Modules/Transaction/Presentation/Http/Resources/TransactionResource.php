<?php

namespace App\Modules\Transaction\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'type' => $this->type->value, 'status' => $this->status->value, 'source_wallet_id' => $this->source_wallet_id, 'destination_wallet_id' => $this->destination_wallet_id, 'amount' => ['minor' => $this->amount_minor, 'currency' => $this->currency_code], 'reference' => $this->reference, 'description' => $this->description, 'ledger_transaction_id' => $this->ledger_transaction_id, 'refunded_transaction_id' => $this->refunded_transaction_id, 'failure' => $this->failure_code ? ['code' => $this->failure_code, 'message' => $this->failure_message] : null, 'completed_at' => $this->completed_at, 'created_at' => $this->created_at];
    }
}
