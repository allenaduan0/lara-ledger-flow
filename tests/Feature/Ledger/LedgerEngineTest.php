<?php

use App\Modules\Ledger\Application\Services\BalanceCalculationService;
use App\Modules\Ledger\Application\Services\LedgerService;
use App\Modules\Ledger\Domain\Data\PostingLine;
use App\Modules\Ledger\Domain\Enums\AccountType;
use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\Ledger\Domain\Exceptions\UnbalancedLedgerTransaction;
use App\Modules\Ledger\Domain\ValueObjects\Money;
use App\Modules\Ledger\Infrastructure\Persistence\Models\Account;
use App\Modules\Ledger\Infrastructure\Persistence\Models\LedgerEntry;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Currency;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Currency::query()->create(['code' => 'USD', 'name' => 'US Dollar', 'minor_unit' => 2, 'is_active' => true]);
});

function liabilityAccount(string $code): Account
{
    return Account::query()->create([
        'code' => $code,
        'name' => $code,
        'type' => AccountType::Liability,
        'normal_balance' => EntryDirection::Credit,
        'currency_code' => 'USD',
        'status' => 'active',
    ]);
}

it('posts a balanced double entry transaction atomically', function () {
    $alice = liabilityAccount('wallet:alice');
    $bob = liabilityAccount('wallet:bob');
    $amount = Money::fromDecimal('100.00', 'USD', 2);

    $transaction = app(LedgerService::class)->post('transfer-001', [
        new PostingLine($alice->id, EntryDirection::Debit, $amount),
        new PostingLine($bob->id, EntryDirection::Credit, $amount),
    ], 'Alice sends Bob USD 100.00');

    expect($transaction->entries)->toHaveCount(2)
        ->and($transaction->entries->sum('amount_minor'))->toBe(20000)
        ->and(app(BalanceCalculationService::class)->forAccount($alice)->balanceMinor)->toBe(-10000)
        ->and(app(BalanceCalculationService::class)->forAccount($bob)->balanceMinor)->toBe(10000);
    $this->assertDatabaseHas('ledger_entries', ['ledger_transaction_id' => $transaction->id, 'direction' => 'debit', 'amount_minor' => 10000]);
    $this->assertDatabaseHas('ledger_entries', ['ledger_transaction_id' => $transaction->id, 'direction' => 'credit', 'amount_minor' => 10000]);
});

it('rejects an unbalanced transaction without writing partial entries', function () {
    $alice = liabilityAccount('wallet:alice');
    $bob = liabilityAccount('wallet:bob');

    expect(fn () => app(LedgerService::class)->post('transfer-unbalanced', [
        new PostingLine($alice->id, EntryDirection::Debit, Money::fromDecimal('100.00', 'USD', 2)),
        new PostingLine($bob->id, EntryDirection::Credit, Money::fromDecimal('99.99', 'USD', 2)),
    ]))->toThrow(UnbalancedLedgerTransaction::class);

    $this->assertDatabaseCount('ledger_transactions', 0);
    $this->assertDatabaseCount('ledger_entries', 0);
});

it('prevents ledger entry updates and deletes at model and database levels', function () {
    $alice = liabilityAccount('wallet:alice');
    $bob = liabilityAccount('wallet:bob');
    $money = Money::fromDecimal('10.00', 'USD', 2);
    $entry = app(LedgerService::class)->post('immutable-001', [
        new PostingLine($alice->id, EntryDirection::Debit, $money),
        new PostingLine($bob->id, EntryDirection::Credit, $money),
    ])->entries->first();

    expect(fn () => $entry->update(['amount_minor' => 1]))->toThrow(LogicException::class);
    expect(fn () => DB::table('ledger_entries')->where('id', $entry->id)->update(['amount_minor' => 1]))->toThrow(QueryException::class);
    expect(fn () => LedgerEntry::query()->findOrFail($entry->id)->delete())->toThrow(LogicException::class);

    $this->assertDatabaseHas('ledger_entries', ['id' => $entry->id, 'amount_minor' => 1000]);
});
