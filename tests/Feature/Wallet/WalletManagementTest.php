<?php

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Wallet\Application\Services\WalletOperationGuard;
use App\Modules\Wallet\Domain\Enums\WalletStatus;
use App\Modules\Wallet\Domain\Exceptions\FrozenWalletException;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Currency;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Currency::query()->create(['code' => 'USD', 'name' => 'US Dollar', 'minor_unit' => 2, 'is_active' => true]);
    Currency::query()->create(['code' => 'AED', 'name' => 'UAE Dirham', 'minor_unit' => 2, 'is_active' => true]);
});

it('allows a user to create a wallet without storing a balance', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/wallets', ['currency' => 'usd', 'name' => 'Primary']);

    $response->assertCreated()
        ->assertJsonPath('data.currency.code', 'USD')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.balance.posted_minor', 0)
        ->assertJsonPath('data.balance.source', 'ledger');
    $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'currency_code' => 'USD']);
    expect(Schema::hasColumn('wallets', 'balance'))->toBeFalse();
});

it('allows one wallet per currency for the same user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/wallets', ['currency' => 'USD'])->assertCreated();
    $this->actingAs($user)->postJson('/api/v1/wallets', ['currency' => 'AED'])->assertCreated();
    $this->actingAs($user)->postJson('/api/v1/wallets', ['currency' => 'USD'])->assertUnprocessable();

    expect($user->wallets()->count())->toBe(2);
});

it('prevents a user from viewing or freezing another users wallet', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $wallet = Wallet::query()->create(['user_id' => $owner->id, 'currency_code' => 'USD', 'status' => WalletStatus::Active]);

    $this->actingAs($intruder)->getJson("/api/v1/wallets/{$wallet->id}")->assertForbidden();
    $this->actingAs($intruder)->postJson("/api/v1/wallets/{$wallet->id}/freeze")->assertForbidden();
});

it('blocks operations while frozen and allows them after unfreezing', function () {
    $user = User::factory()->create();
    $wallet = Wallet::query()->create(['user_id' => $user->id, 'currency_code' => 'USD', 'status' => WalletStatus::Active]);

    $this->actingAs($user)->postJson("/api/v1/wallets/{$wallet->id}/freeze", ['reason' => 'Customer request'])
        ->assertOk()->assertJsonPath('data.status', 'frozen');

    expect(fn () => app(WalletOperationGuard::class)->ensureCanOperate($wallet->refresh()))
        ->toThrow(FrozenWalletException::class);

    $this->actingAs($user)->postJson("/api/v1/wallets/{$wallet->id}/unfreeze")
        ->assertOk()->assertJsonPath('data.status', 'active');

    app(WalletOperationGuard::class)->ensureCanOperate($wallet->refresh());
});
