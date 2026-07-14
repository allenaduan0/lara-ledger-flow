<?php

use App\Modules\Identity\Domain\Authorization\RoleName;
use App\Modules\Identity\Infrastructure\Persistence\Models\Role;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Ledger\Application\Services\LedgerService;
use App\Modules\Ledger\Domain\Data\PostingLine;
use App\Modules\Ledger\Domain\Enums\AccountType;
use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\Ledger\Domain\ValueObjects\Money;
use App\Modules\Ledger\Infrastructure\Persistence\Models\Account;
use App\Modules\Ledger\Infrastructure\Persistence\Models\LedgerEntry;
use App\Modules\Ledger\Infrastructure\Persistence\Models\LedgerTransaction;
use App\Modules\Reconciliation\Application\Actions\RunDailyReconciliationAction;
use App\Modules\Transaction\Domain\Enums\TransactionStatus;
use App\Modules\Transaction\Domain\Enums\TransactionType;
use App\Modules\Transaction\Infrastructure\Persistence\Models\FinancialTransaction;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Currency::query()->create(['code' => 'USD', 'name' => 'US Dollar', 'minor_unit' => 2, 'is_active' => true]);
    Role::query()->create(['name' => RoleName::Administrator->value]);
});

function operationsAdmin(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('name', RoleName::Administrator->value)->firstOrFail());

    return $user;
}

function operationsAccount(string $code): Account
{
    return Account::query()->create(['code' => $code, 'name' => $code, 'type' => AccountType::Liability, 'normal_balance' => EntryDirection::Credit, 'currency_code' => 'USD', 'status' => 'active']);
}

function monitoredTransaction(User $user, string $reference, TransactionStatus $status): FinancialTransaction
{
    return FinancialTransaction::query()->create(['initiated_by' => $user->id, 'type' => TransactionType::Transfer, 'status' => $status, 'currency_code' => 'USD', 'amount_minor' => 1000, 'reference' => $reference]);
}

it('restricts dashboard APIs and views to administrators', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)->getJson('/api/v1/admin/dashboard')->assertForbidden();
    $this->actingAs($customer)->get('/admin')->assertForbidden();
});

it('redirects unauthenticated operators to the admin login', function () {
    $this->get('/admin')->assertRedirect('/login');
});

it('lets administrators search and filter monitored transactions', function () {
    $admin = operationsAdmin();
    monitoredTransaction($admin, 'ops-completed-001', TransactionStatus::Completed);
    monitoredTransaction($admin, 'ops-failed-001', TransactionStatus::Failed);

    $this->actingAs($admin)->getJson('/api/v1/admin/transactions?status=failed&q=ops-failed')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reference', 'ops-failed-001')->assertJsonPath('data.0.status', 'failed');

    $this->actingAs($admin)->get('/admin/transactions?status=completed')
        ->assertOk()->assertSee('ops-completed-001')->assertDontSee('ops-failed-001');
});

it('shows debit and credit accounts with journal balance verification', function () {
    $admin = operationsAdmin();
    $debit = operationsAccount('ops:debit');
    $credit = operationsAccount('ops:credit');
    $journal = app(LedgerService::class)->post('ops-ledger-balanced', [
        new PostingLine($debit->id, EntryDirection::Debit, Money::fromDecimal('100.00', 'USD', 2)),
        new PostingLine($credit->id, EntryDirection::Credit, Money::fromDecimal('100.00', 'USD', 2)),
    ]);

    $this->actingAs($admin)->getJson("/api/v1/admin/ledger/{$journal->id}")
        ->assertOk()->assertJsonPath('data.balanced', true)->assertJsonPath('data.verification.USD.difference_minor', 0);
    $this->actingAs($admin)->get("/admin/ledger/{$journal->id}")
        ->assertOk()->assertSee('DEBIT')->assertSee('CREDIT')->assertSee('Balanced');
});

it('generates a balanced daily reconciliation report', function () {
    $admin = operationsAdmin();
    $debit = operationsAccount('recon:debit');
    $credit = operationsAccount('recon:credit');
    app(LedgerService::class)->post('recon-balanced', [
        new PostingLine($debit->id, EntryDirection::Debit, Money::fromDecimal('10000.00', 'USD', 2)),
        new PostingLine($credit->id, EntryDirection::Credit, Money::fromDecimal('10000.00', 'USD', 2)),
    ]);

    $report = app(RunDailyReconciliationAction::class)->execute(today('UTC')->toDateString(), $admin->id)->firstWhere('currency_code', 'USD');

    expect($report->expected_minor)->toBe(1000000)
        ->and($report->ledger_minor)->toBe(1000000)
        ->and($report->difference_minor)->toBe(0)
        ->and($report->status)->toBe('balanced');
});

it('detects and records an unbalanced journal difference', function () {
    $admin = operationsAdmin();
    $account = operationsAccount('recon:broken');
    $journal = LedgerTransaction::query()->create(['reference' => 'recon-broken', 'posted_at' => now()]);
    LedgerEntry::query()->create(['ledger_transaction_id' => $journal->id, 'account_id' => $account->id, 'direction' => EntryDirection::Debit, 'amount_minor' => 2500, 'created_at' => now()]);

    $report = app(RunDailyReconciliationAction::class)->execute(today('UTC')->toDateString(), $admin->id)->firstWhere('currency_code', 'USD');

    expect($report->status)->toBe('discrepancy')->and($report->difference_minor)->toBe(-2500)->and($report->invalid_journal_count)->toBe(1);
    $this->assertDatabaseHas('reconciliation_discrepancies', ['reconciliation_report_id' => $report->id, 'type' => 'unbalanced_journal', 'reference' => 'recon-broken', 'difference_minor' => -2500]);
    $this->actingAs($admin)->get("/admin/reconciliation/{$report->id}")->assertOk()->assertSee('Difference')->assertSee('unbalanced journal');
});

it('renders the administrator dashboard and supports session login', function () {
    $admin = operationsAdmin();

    $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect(route('admin.dashboard'));
    $this->get('/admin')->assertOk()->assertSee('Operations overview')->assertSee('Recent transaction');
});
