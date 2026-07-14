<?php

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Transaction\Infrastructure\Persistence\Models\FinancialTransaction;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('ensures every completed financial transaction has a balanced journal per currency', function () {
    Currency::query()->create(['code' => 'USD', 'name' => 'US Dollar', 'minor_unit' => 2, 'is_active' => true]);
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $aliceWallet = $this->actingAs($alice)->postJson('/api/v1/wallets', ['currency' => 'USD'])->assertCreated()->json('data.id');
    $bobWallet = $this->actingAs($bob)->postJson('/api/v1/wallets', ['currency' => 'USD'])->assertCreated()->json('data.id');

    $this->actingAs($alice)->postJson('/api/v1/transactions/deposits', [
        'wallet_id' => $aliceWallet, 'amount' => '200.00', 'reference' => 'integrity-deposit',
    ], ['Idempotency-Key' => 'integrity-deposit-key'])->assertCreated();

    $transferId = $this->actingAs($alice)->postJson('/api/v1/transactions/transfers', [
        'source_wallet_id' => $aliceWallet, 'destination_wallet_id' => $bobWallet, 'amount' => '75.00', 'reference' => 'integrity-transfer',
    ], ['Idempotency-Key' => 'integrity-transfer-key'])->assertCreated()->json('data.id');

    $this->actingAs($alice)->postJson('/api/v1/transactions/withdrawals', [
        'wallet_id' => $aliceWallet, 'amount' => '25.00', 'reference' => 'integrity-withdrawal',
    ], ['Idempotency-Key' => 'integrity-withdrawal-key'])->assertCreated();

    $this->actingAs($alice)->postJson("/api/v1/transactions/{$transferId}/refunds", [
        'reference' => 'integrity-refund',
    ], ['Idempotency-Key' => 'integrity-refund-key'])->assertCreated();

    $completed = FinancialTransaction::query()->where('status', 'completed')->get();
    expect($completed)->toHaveCount(3);

    foreach ($completed as $transaction) {
        expect($transaction->ledger_transaction_id)->not->toBeNull();

        $totals = DB::table('ledger_entries')
            ->join('accounts', 'accounts.id', '=', 'ledger_entries.account_id')
            ->where('ledger_entries.ledger_transaction_id', $transaction->ledger_transaction_id)
            ->selectRaw("accounts.currency_code, SUM(CASE WHEN direction = 'debit' THEN amount_minor ELSE 0 END) AS debits, SUM(CASE WHEN direction = 'credit' THEN amount_minor ELSE 0 END) AS credits")
            ->groupBy('accounts.currency_code')
            ->get();

        expect($totals)->not->toBeEmpty();
        foreach ($totals as $total) {
            expect((int) $total->debits)->toBeGreaterThan(0)
                ->and((int) $total->credits)->toBe((int) $total->debits);
        }
    }

    expect(FinancialTransaction::query()->where('status', 'reversed')->whereNull('ledger_transaction_id')->exists())->toBeFalse();
});
