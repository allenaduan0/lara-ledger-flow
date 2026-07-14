<?php

namespace App\Modules\Transaction\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['reference' => ['required', 'string', 'min:3', 'max:120', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]*$/', 'unique:transactions,reference'], 'description' => ['nullable', 'string', 'max:255']];
    }
}
