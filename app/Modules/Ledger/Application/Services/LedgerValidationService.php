<?php

namespace App\Modules\Ledger\Application\Services;

use App\Modules\Ledger\Domain\Data\PostingLine;
use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\Ledger\Domain\Exceptions\UnbalancedLedgerTransaction;
use InvalidArgumentException;
use OverflowException;

final class LedgerValidationService
{
    /** @param list<PostingLine> $lines */
    public function validate(array $lines): void
    {
        if (count($lines) < 2) {
            throw new UnbalancedLedgerTransaction;
        }

        $totals = [];
        foreach ($lines as $line) {
            if (! $line instanceof PostingLine) {
                throw new InvalidArgumentException('Every ledger line must be a PostingLine.');
            }

            $currency = $line->money->currency;
            $totals[$currency] ??= ['debit' => 0, 'credit' => 0];
            $key = $line->direction === EntryDirection::Debit ? 'debit' : 'credit';
            $totals[$currency][$key] = $this->checkedAdd($totals[$currency][$key], $line->money->minor);
        }

        foreach ($totals as $total) {
            if ($total['debit'] === 0 || $total['credit'] === 0 || $total['debit'] !== $total['credit']) {
                throw new UnbalancedLedgerTransaction;
            }
        }
    }

    private function checkedAdd(int $left, int $right): int
    {
        if ($right > PHP_INT_MAX - $left) {
            throw new OverflowException('Ledger total exceeds the supported range.');
        }

        return $left + $right;
    }
}
