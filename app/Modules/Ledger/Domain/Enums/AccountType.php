<?php

namespace App\Modules\Ledger\Domain\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    public function normalBalance(): EntryDirection
    {
        return match ($this) {
            self::Asset, self::Expense => EntryDirection::Debit,
            self::Liability, self::Equity, self::Revenue => EntryDirection::Credit,
        };
    }
}
