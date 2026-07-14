<?php

namespace App\Modules\Reporting\Application\Services;

use App\Modules\Transaction\Infrastructure\Persistence\Models\FinancialTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class TransactionMonitoringService
{
    public function search(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return FinancialTransaction::query()
            ->with(['currency', 'initiator:id,name,email', 'sourceWallet:id,name,currency_code', 'destinationWallet:id,name,currency_code'])
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(fn ($nested) => $nested
                ->where('id', $term)->orWhere('reference', 'like', "%{$term}%")->orWhereHas('initiator', fn ($user) => $user->where('email', 'like', "%{$term}%"))))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['currency'] ?? null, fn ($query, $currency) => $query->where('currency_code', strtoupper($currency)))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('created_at')
            ->paginate(min(max($perPage, 1), 100))
            ->withQueryString();
    }

    public function details(FinancialTransaction $transaction): FinancialTransaction
    {
        return $transaction->load(['currency', 'initiator:id,name,email', 'sourceWallet.user:id,name,email', 'destinationWallet.user:id,name,email', 'statusHistory', 'ledgerTransaction.entries.account']);
    }
}
