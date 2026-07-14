<?php

namespace App\Modules\Transaction\Presentation\Http\Requests;

class TransferRequest extends MoneyMovementRequest
{
    public function rules(): array
    {
        return [...$this->commonRules(['source_wallet_id']), 'source_wallet_id' => ['required', 'ulid', 'exists:wallets,id'], 'destination_wallet_id' => ['required', 'ulid', 'different:source_wallet_id', 'exists:wallets,id']];
    }
}
