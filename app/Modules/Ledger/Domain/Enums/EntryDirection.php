<?php

namespace App\Modules\Ledger\Domain\Enums;

enum EntryDirection: string
{
    case Debit = 'debit';
    case Credit = 'credit';
}
