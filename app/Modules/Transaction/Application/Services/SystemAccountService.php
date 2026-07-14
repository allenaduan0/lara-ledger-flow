<?php

namespace App\Modules\Transaction\Application\Services;

use App\Modules\Ledger\Domain\Enums\AccountType;
use App\Modules\Ledger\Infrastructure\Persistence\Models\Account;

final class SystemAccountService
{
    public function settlement(string $currency): Account
    {
        $type = AccountType::Asset;

        return Account::query()->firstOrCreate(
            ['code' => 'settlement:'.strtoupper($currency)],
            ['name' => strtoupper($currency).' Settlement Asset', 'type' => $type, 'normal_balance' => $type->normalBalance(), 'currency_code' => strtoupper($currency), 'status' => 'active'],
        );
    }
}
