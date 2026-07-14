<?php

namespace App\Modules\Transaction\Presentation\Http\Requests;

use App\Modules\Transaction\Presentation\Http\Rules\ValidWalletMoney;
use Illuminate\Foundation\Http\FormRequest;

abstract class MoneyMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @param list<string> $walletFields */
    protected function commonRules(array $walletFields): array
    {
        return [
            'amount' => ['required', 'string', 'max:30', 'regex:/^(0|[1-9]\d*)(\.\d+)?$/', new ValidWalletMoney($walletFields)],
            'reference' => ['required', 'string', 'min:3', 'max:120', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]*$/', 'unique:transactions,reference'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
