<?php

namespace App\Modules\Ledger\Application\Services;

use App\Modules\Ledger\Domain\Data\AccountBalance;
use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\Ledger\Infrastructure\Persistence\Models\Account;
use App\Modules\Ledger\Infrastructure\Persistence\Models\LedgerEntry;

final class BalanceCalculationService
{
    public function forAccount(Account $account): AccountBalance
    {
        $totals = LedgerEntry::query()->where('account_id', $account->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount_minor ELSE 0 END), 0) AS debits_minor")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount_minor ELSE 0 END), 0) AS credits_minor")
            ->firstOrFail();

        $debits = (int) $totals->debits_minor;
        $credits = (int) $totals->credits_minor;
        $balance = $account->normal_balance === EntryDirection::Debit ? $debits - $credits : $credits - $debits;

        return new AccountBalance($debits, $credits, $balance, $account->currency_code);
    }
}
