<?php

namespace App\Modules\Transaction\Domain\Enums;

enum TransactionType: string
{
    case Transfer = 'transfer';
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
    case Refund = 'refund';
}
