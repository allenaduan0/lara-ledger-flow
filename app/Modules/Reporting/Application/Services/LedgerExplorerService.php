<?php

namespace App\Modules\Reporting\Application\Services;

use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\Ledger\Infrastructure\Persistence\Models\LedgerTransaction;

final class LedgerExplorerService
{
    public function inspect(LedgerTransaction $transaction): array
    {
        $transaction->load('entries.account');
        $currencies = [];
        foreach ($transaction->entries as $entry) {
            $currency = $entry->account->currency_code;
            $currencies[$currency] ??= ['debits_minor' => 0, 'credits_minor' => 0];
            $key = $entry->direction === EntryDirection::Debit ? 'debits_minor' : 'credits_minor';
            $currencies[$currency][$key] += $entry->amount_minor;
        }
        foreach ($currencies as &$totals) {
            $totals['difference_minor'] = $totals['credits_minor'] - $totals['debits_minor'];
            $totals['balanced'] = $totals['difference_minor'] === 0;
        }

        return ['transaction' => $transaction, 'verification' => $currencies, 'balanced' => $currencies !== [] && collect($currencies)->every(fn (array $totals) => $totals['balanced'])];
    }
}
