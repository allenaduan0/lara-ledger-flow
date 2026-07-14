<?php

use App\Modules\Audit\Application\Listeners\CreateAuditEntry;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Notification\Application\Listeners\SendTransactionNotification;
use App\Modules\Reporting\Application\Listeners\GenerateStatement;
use App\Modules\Reporting\Application\Listeners\UpdateReportingData;
use App\Modules\Transaction\Domain\Events\TransactionCreated;
use App\Modules\Transaction\Domain\Events\TransferCompleted;
use App\Modules\Transaction\Domain\Events\TransferFailed;
use App\Modules\Transaction\Domain\Events\WalletCredited;
use App\Modules\Transaction\Domain\Events\WalletDebited;
use App\Modules\Transaction\Infrastructure\Persistence\Models\FinancialTransaction;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Currency;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Currency::query()->create(['code' => 'USD', 'name' => 'US Dollar', 'minor_unit' => 2, 'is_active' => true]);
});

function eventWallet($test, User $user): string
{
    return $test->actingAs($user)->postJson('/api/v1/wallets', ['currency' => 'USD'])->assertCreated()->json('data.id');
}

it('emits financial domain events from committed transfer workflows', function () {
    Event::fake([TransactionCreated::class, TransferCompleted::class, TransferFailed::class, WalletCredited::class, WalletDebited::class]);
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $aliceWallet = eventWallet($this, $alice);
    $bobWallet = eventWallet($this, $bob);

    $this->actingAs($alice)->postJson('/api/v1/transactions/deposits', ['wallet_id' => $aliceWallet, 'amount' => '50.00', 'reference' => 'event-deposit'], ['Idempotency-Key' => 'event-deposit-key'])->assertCreated();
    $transferId = $this->actingAs($alice)->postJson('/api/v1/transactions/transfers', ['source_wallet_id' => $aliceWallet, 'destination_wallet_id' => $bobWallet, 'amount' => '20.00', 'reference' => 'event-transfer'], ['Idempotency-Key' => 'event-transfer-key'])->assertCreated()->json('data.id');

    Event::assertDispatched(TransactionCreated::class, 2);
    Event::assertDispatched(TransferCompleted::class, fn ($event) => $event->transactionId === $transferId);
    Event::assertDispatched(WalletDebited::class, fn ($event) => $event->transactionId === $transferId && $event->walletId === $aliceWallet);
    Event::assertDispatched(WalletCredited::class, fn ($event) => $event->transactionId === $transferId && $event->walletId === $bobWallet);
    Event::assertNotDispatched(TransferFailed::class);
});

it('emits transfer failed after a deterministic processing failure', function () {
    Event::fake([TransferFailed::class]);
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $aliceWallet = eventWallet($this, $alice);
    $bobWallet = eventWallet($this, $bob);

    $response = $this->actingAs($alice)->postJson('/api/v1/transactions/transfers', ['source_wallet_id' => $aliceWallet, 'destination_wallet_id' => $bobWallet, 'amount' => '1.00', 'reference' => 'failed-event-transfer'], ['Idempotency-Key' => 'failed-event-key'])->assertUnprocessable();

    Event::assertDispatched(TransferFailed::class, fn ($event) => $event->transactionId === $response->json('transaction_id'));
});

it('runs projection listeners idempotently under at least once delivery', function () {
    Event::fake();
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $aliceWallet = eventWallet($this, $alice);
    $bobWallet = eventWallet($this, $bob);
    $this->actingAs($alice)->postJson('/api/v1/transactions/deposits', ['wallet_id' => $aliceWallet, 'amount' => '50.00', 'reference' => 'projection-deposit'], ['Idempotency-Key' => 'projection-deposit-key'])->assertCreated();
    $transactionId = $this->actingAs($alice)->postJson('/api/v1/transactions/transfers', ['source_wallet_id' => $aliceWallet, 'destination_wallet_id' => $bobWallet, 'amount' => '10.00', 'reference' => 'projection-transfer'], ['Idempotency-Key' => 'projection-transfer-key'])->assertCreated()->json('data.id');
    $transaction = FinancialTransaction::query()->with('ledgerTransaction.entries.account')->findOrFail($transactionId);
    $creditEntry = $transaction->ledgerTransaction->entries->first(fn ($entry) => $entry->account->wallet_id === $bobWallet);
    $credited = new WalletCredited($transaction->id, $bobWallet, $creditEntry->id, $creditEntry->amount_minor, 'USD');
    $completed = new TransferCompleted($transaction->id, $alice->id, $aliceWallet, $bobWallet, 1000, 'USD');

    app(GenerateStatement::class)->handle($credited);
    app(GenerateStatement::class)->handle($credited);
    app(SendTransactionNotification::class)->handle($credited);
    app(SendTransactionNotification::class)->handle($credited);
    app(UpdateReportingData::class)->handle($completed);
    app(UpdateReportingData::class)->handle($completed);
    app(CreateAuditEntry::class)->handle($completed);
    app(CreateAuditEntry::class)->handle($completed);

    $this->assertDatabaseCount('statement_entries', 1);
    $this->assertDatabaseCount('transaction_notifications', 1);
    $this->assertDatabaseHas('reporting_daily_metrics', ['currency_code' => 'USD', 'transfers_completed' => 1, 'transferred_minor' => 1000]);
    $this->assertDatabaseCount('processed_reporting_events', 1);
    $this->assertDatabaseHas('audit_logs', ['event_id' => $completed->eventId]);
});

it('configures queued listeners with database retries and records permanent failures', function () {
    $listeners = [app(SendTransactionNotification::class), app(GenerateStatement::class), app(UpdateReportingData::class), app(CreateAuditEntry::class)];
    foreach ($listeners as $listener) {
        expect($listener)->toBeInstanceOf(ShouldQueue::class)
            ->and($listener->connection)->toBe('database')
            ->and($listener->tries)->toBe(3)
            ->and($listener->backoff())->toBe([5, 30, 120]);
    }
    expect(config('queue.connections.database.after_commit'))->toBeTrue();

    $event = new TransactionCreated('01TESTTRANSACTION0000000000', 1, 'transfer', 1000, 'USD');
    $listeners[0]->failed($event, new RuntimeException('Notification provider unavailable'));

    $this->assertDatabaseHas('audit_logs', ['action' => 'event_listener.failed', 'subject_type' => SendTransactionNotification::class]);
});
