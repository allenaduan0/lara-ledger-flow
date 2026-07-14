<?php

namespace App\Modules\Transaction\Infrastructure\Policies;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Transaction\Infrastructure\Persistence\Models\FinancialTransaction;

final class TransactionPolicy
{
    public function view(User $user, FinancialTransaction $transaction): bool
    {
        return $transaction->initiated_by === $user->getKey();
    }

    public function refund(User $user, FinancialTransaction $transaction): bool
    {
        return $this->view($user, $transaction);
    }
}
