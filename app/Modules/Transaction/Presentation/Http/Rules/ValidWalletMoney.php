<?php

namespace App\Modules\Transaction\Presentation\Http\Rules;

use App\Modules\Ledger\Domain\ValueObjects\Money;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

final class ValidWalletMoney implements DataAwareRule, ValidationRule
{
    private array $data = [];

    /** @param list<string> $walletFields */
    public function __construct(private readonly array $walletFields) {}

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $walletId = collect($this->walletFields)->map(fn (string $field) => $this->data[$field] ?? null)->first();
        if (! is_string($value) || ! $walletId) {
            return;
        }

        $wallet = Wallet::query()->with('currency')->find($walletId);
        if (! $wallet?->currency) {
            return;
        }

        try {
            Money::fromDecimal($value, $wallet->currency_code, $wallet->currency->minor_unit);
        } catch (Throwable) {
            $fail("The {$attribute} must be greater than zero, fit the supported range, and use no more than {$wallet->currency->minor_unit} decimal places for {$wallet->currency_code}.");
        }
    }
}
