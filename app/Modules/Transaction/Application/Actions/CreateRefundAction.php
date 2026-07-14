<?php

namespace App\Modules\Transaction\Application\Actions;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Transaction\Application\Services\TransactionProcessingService;
use App\Modules\Transaction\Infrastructure\Persistence\Models\FinancialTransaction;

final class CreateRefundAction
{
    public function __construct(private readonly TransactionProcessingService $processor) {}

    public function execute(User $user, FinancialTransaction $original, array $data): FinancialTransaction
    {
        return $this->processor->refund($user, $original, $data['reference'], $data['description'] ?? null);
    }
}
