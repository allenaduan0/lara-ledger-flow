<?php

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Transaction\Domain\Events\TransactionCompleted;
use App\Modules\Transaction\Infrastructure\Persistence\Models\FinancialTransaction;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Currency::query()->create(['code' => 'USD', 'name' => 'US Dollar', 'minor_unit' => 2, 'is_active' => true]);
});

function createUsdWallet($test, User $user): string
{
    return $test->actingAs($user)->postJson('/api/v1/wallets', ['currency' => 'USD'])->assertCreated()->json('data.id');
}

function depositFunds($test, User $user, string $walletId, string $amount, string $reference): array
{
    return $test->actingAs($user)->postJson('/api/v1/transactions/deposits', ['wallet_id' => $walletId, 'amount' => $amount, 'reference' => $reference], ['Idempotency-Key' => 'idem-'.$reference])->assertCreated()->json('data');
}

it('transfers money atomically and dispatches an event after completion', function () {
    Event::fake([TransactionCompleted::class]);
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $aliceWallet = createUsdWallet($this, $alice);
    $bobWallet = createUsdWallet($this, $bob);
    depositFunds($this, $alice, $aliceWallet, '200.00', 'deposit-alice-001');

    $transaction = $this->actingAs($alice)->postJson('/api/v1/transactions/transfers', [
        'source_wallet_id' => $aliceWallet,
        'destination_wallet_id' => $bobWallet,
        'amount' => '100.00',
        'reference' => 'transfer-001',
    ], ['Idempotency-Key' => 'idem-transfer-001'])->assertCreated()->assertJsonPath('data.status', 'completed')->json('data');

    $this->actingAs($alice)->getJson("/api/v1/wallets/{$aliceWallet}")->assertJsonPath('data.balance.posted_minor', 10000);
    $this->actingAs($bob)->getJson("/api/v1/wallets/{$bobWallet}")->assertJsonPath('data.balance.posted_minor', 10000);
    $this->assertDatabaseCount('ledger_entries', 4);
    $this->assertDatabaseCount('transaction_status_history', 8);
    $this->assertDatabaseHas('audit_logs', ['action' => 'transaction.status_changed', 'subject_id' => $transaction['id']]);
    Event::assertDispatched(TransactionCompleted::class, fn ($event) => $event->transactionId === $transaction['id']);
});

it('processes withdrawals and prevents a negative wallet balance', function () {
    $user = User::factory()->create();
    $wallet = createUsdWallet($this, $user);
    depositFunds($this, $user, $wallet, '50.00', 'deposit-withdrawal-001');

    $this->actingAs($user)->postJson('/api/v1/transactions/withdrawals', ['wallet_id' => $wallet, 'amount' => '20.00', 'reference' => 'withdrawal-001'], ['Idempotency-Key' => 'idem-withdrawal-001'])
        ->assertCreated()->assertJsonPath('data.status', 'completed');
    $this->actingAs($user)->postJson('/api/v1/transactions/withdrawals', ['wallet_id' => $wallet, 'amount' => '31.00', 'reference' => 'withdrawal-too-large'], ['Idempotency-Key' => 'idem-withdrawal-too-large'])
        ->assertUnprocessable()->assertJsonPath('code', 'TRANSACTION_FAILED');

    $this->actingAs($user)->getJson("/api/v1/wallets/{$wallet}")->assertJsonPath('data.balance.posted_minor', 3000);
    $this->assertDatabaseHas('transactions', ['reference' => 'withdrawal-too-large', 'status' => 'failed', 'ledger_transaction_id' => null]);
});

it('refunds a completed transfer with a compensating journal', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $aliceWallet = createUsdWallet($this, $alice);
    $bobWallet = createUsdWallet($this, $bob);
    depositFunds($this, $alice, $aliceWallet, '100.00', 'deposit-refund-001');
    $originalId = $this->actingAs($alice)->postJson('/api/v1/transactions/transfers', ['source_wallet_id' => $aliceWallet, 'destination_wallet_id' => $bobWallet, 'amount' => '40.00', 'reference' => 'transfer-refund-001'], ['Idempotency-Key' => 'idem-transfer-refund-001'])
        ->assertCreated()->json('data.id');

    $this->actingAs($alice)->postJson("/api/v1/transactions/{$originalId}/refunds", ['reference' => 'refund-001'], ['Idempotency-Key' => 'idem-refund-001'])
        ->assertCreated()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.refunded_transaction_id', $originalId);

    expect(FinancialTransaction::query()->findOrFail($originalId)->status->value)->toBe('reversed');
    $this->actingAs($alice)->getJson("/api/v1/wallets/{$aliceWallet}")->assertJsonPath('data.balance.posted_minor', 10000);
    $this->actingAs($bob)->getJson("/api/v1/wallets/{$bobWallet}")->assertJsonPath('data.balance.posted_minor', 0);
});

it('does not allow a user to spend from another users wallet', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $recipient = User::factory()->create();
    $source = createUsdWallet($this, $owner);
    $destination = createUsdWallet($this, $recipient);
    depositFunds($this, $owner, $source, '20.00', 'deposit-owner-001');

    $this->actingAs($intruder)->postJson('/api/v1/transactions/transfers', ['source_wallet_id' => $source, 'destination_wallet_id' => $destination, 'amount' => '1.00', 'reference' => 'unauthorized-transfer'], ['Idempotency-Key' => 'idem-unauthorized-transfer'])
        ->assertForbidden();
    $this->assertDatabaseMissing('transactions', ['reference' => 'unauthorized-transfer']);
});

it('creates only one transaction when an identical transfer is retried', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $aliceWallet = createUsdWallet($this, $alice);
    $bobWallet = createUsdWallet($this, $bob);
    depositFunds($this, $alice, $aliceWallet, '100.00', 'deposit-idempotency-001');
    $payload = ['source_wallet_id' => $aliceWallet, 'destination_wallet_id' => $bobWallet, 'amount' => '25.00', 'reference' => 'transfer-idempotency-001'];
    $headers = ['Idempotency-Key' => 'client-transfer-key-001'];

    $first = $this->actingAs($alice)->postJson('/api/v1/transactions/transfers', $payload, $headers)->assertCreated();
    $second = $this->actingAs($alice)->postJson('/api/v1/transactions/transfers', $payload, $headers)->assertCreated();

    expect($second->json('data.id'))->toBe($first->json('data.id'));
    $second->assertHeader('Idempotent-Replayed', 'true');
    $this->assertDatabaseCount('transactions', 2);
    $this->assertDatabaseCount('ledger_transactions', 2);
    $this->assertDatabaseHas('idempotency_keys', ['user_id' => $alice->id, 'key' => 'client-transfer-key-001', 'status' => 'completed', 'transaction_id' => $first->json('data.id')]);
});

it('rejects idempotency key reuse with a different request', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $aliceWallet = createUsdWallet($this, $alice);
    $bobWallet = createUsdWallet($this, $bob);
    depositFunds($this, $alice, $aliceWallet, '100.00', 'deposit-idempotency-conflict');
    $headers = ['Idempotency-Key' => 'client-transfer-conflict'];
    $payload = ['source_wallet_id' => $aliceWallet, 'destination_wallet_id' => $bobWallet, 'amount' => '10.00', 'reference' => 'transfer-conflict-001'];

    $this->actingAs($alice)->postJson('/api/v1/transactions/transfers', $payload, $headers)->assertCreated();
    $payload['amount'] = '11.00';
    $this->actingAs($alice)->postJson('/api/v1/transactions/transfers', $payload, $headers)
        ->assertConflict()->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');

    $this->assertDatabaseCount('transactions', 2);
});

it('requires an idempotency key for all money movement requests', function () {
    $user = User::factory()->create();
    $wallet = createUsdWallet($this, $user);

    $this->actingAs($user)->postJson('/api/v1/transactions/deposits', ['wallet_id' => $wallet, 'amount' => '10.00', 'reference' => 'missing-key'])
        ->assertBadRequest()->assertJsonPath('code', 'IDEMPOTENCY_KEY_REQUIRED');
    $this->assertDatabaseCount('transactions', 0);
});

it('replays a failed request without processing it twice', function () {
    $user = User::factory()->create();
    $wallet = createUsdWallet($this, $user);
    $payload = ['wallet_id' => $wallet, 'amount' => '1.00', 'reference' => 'failed-idempotent-withdrawal'];
    $headers = ['Idempotency-Key' => 'failed-withdrawal-key'];

    $first = $this->actingAs($user)->postJson('/api/v1/transactions/withdrawals', $payload, $headers)->assertUnprocessable();
    $second = $this->actingAs($user)->postJson('/api/v1/transactions/withdrawals', $payload, $headers)->assertUnprocessable();

    expect($second->json('transaction_id'))->toBe($first->json('transaction_id'));
    $second->assertHeader('Idempotent-Replayed', 'true');
    $this->assertDatabaseCount('transactions', 1);
    $this->assertDatabaseCount('ledger_transactions', 0);
    $this->assertDatabaseHas('audit_logs', ['action' => 'financial_request.failed', 'subject_type' => 'idempotency_key']);
});

it('recovers a stale processing key from an already committed transaction', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $aliceWallet = createUsdWallet($this, $alice);
    $bobWallet = createUsdWallet($this, $bob);
    depositFunds($this, $alice, $aliceWallet, '100.00', 'deposit-recovery-001');
    $payload = ['source_wallet_id' => $aliceWallet, 'destination_wallet_id' => $bobWallet, 'amount' => '15.00', 'reference' => 'transfer-recovery-001'];
    $headers = ['Idempotency-Key' => 'client-recovery-key-001'];
    $first = $this->actingAs($alice)->postJson('/api/v1/transactions/transfers', $payload, $headers)->assertCreated();

    DB::table('idempotency_keys')->where('key', 'client-recovery-key-001')->update([
        'status' => 'processing', 'response_status' => null, 'response' => null,
        'transaction_id' => null, 'completed_at' => null, 'locked_until' => now()->subMinute(),
    ]);

    $recovered = $this->actingAs($alice)->postJson('/api/v1/transactions/transfers', $payload, $headers)->assertCreated();

    expect($recovered->json('data.id'))->toBe($first->json('data.id'));
    $recovered->assertHeader('Idempotent-Replayed', 'true');
    $this->assertDatabaseCount('transactions', 2);
    $this->assertDatabaseCount('ledger_transactions', 2);
});
