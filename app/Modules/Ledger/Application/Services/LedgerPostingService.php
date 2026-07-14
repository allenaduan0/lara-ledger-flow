<?php

namespace App\Modules\Ledger\Application\Services;

use App\Modules\Ledger\Domain\Data\PostingLine;
use App\Modules\Ledger\Infrastructure\Persistence\Models\Account;
use App\Modules\Ledger\Infrastructure\Persistence\Models\LedgerEntry;
use App\Modules\Ledger\Infrastructure\Persistence\Models\LedgerTransaction;
use DomainException;
use Illuminate\Support\Facades\DB;

final class LedgerPostingService
{
    public function __construct(private readonly LedgerValidationService $validator) {}

    /** @param list<PostingLine> $lines */
    public function post(string $reference, array $lines, ?string $description = null): LedgerTransaction
    {
        $this->validator->validate($lines);

        return DB::transaction(function () use ($reference, $lines, $description): LedgerTransaction {
            $accountIds = collect($lines)->pluck('accountId')->unique()->sort()->values();
            $accounts = Account::query()->whereIn('id', $accountIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            if ($accounts->count() !== $accountIds->count()) {
                throw new DomainException('One or more ledger accounts do not exist.');
            }

            foreach ($lines as $line) {
                $account = $accounts->get($line->accountId);
                if ($account->status !== 'active' || $account->currency_code !== $line->money->currency) {
                    throw new DomainException('Ledger account is inactive or has a different currency.');
                }
            }

            $transaction = LedgerTransaction::query()->create([
                'reference' => $reference,
                'description' => $description,
                'posted_at' => now(),
            ]);

            foreach ($lines as $line) {
                LedgerEntry::query()->create([
                    'ledger_transaction_id' => $transaction->id,
                    'account_id' => $line->accountId,
                    'direction' => $line->direction,
                    'amount_minor' => $line->money->minor,
                    'created_at' => $transaction->posted_at,
                ]);
            }

            return $transaction->load('entries.account');
        }, attempts: 3);
    }
}
