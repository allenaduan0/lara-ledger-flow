<?php

namespace App\Modules\Wallet\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['currency' => strtoupper((string) $this->input('currency'))]);
    }

    public function rules(): array
    {
        return [
            'currency' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')->where('is_active', true), Rule::unique('wallets', 'currency_code')->where('user_id', $this->user()->getKey())],
            'name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
