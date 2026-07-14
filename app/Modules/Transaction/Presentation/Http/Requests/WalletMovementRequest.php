<?php

namespace App\Modules\Transaction\Presentation\Http\Requests;

class WalletMovementRequest extends MoneyMovementRequest
{
    public function rules(): array
    {
        return [...$this->commonRules(['wallet_id']), 'wallet_id' => ['required', 'ulid', 'exists:wallets,id']];
    }
}
