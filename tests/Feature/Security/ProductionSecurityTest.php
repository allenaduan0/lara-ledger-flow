<?php

use App\Modules\Audit\Application\Services\AuditService;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Currency::query()->create(['code' => 'EUR', 'name' => 'Euro', 'minor_unit' => 2, 'is_active' => true]);
});

it('adds browser security headers', function () {
    $this->get('/login')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Content-Security-Policy');
});

it('rate limits repeated authentication attempts', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/v1/auth/login', ['email' => 'limited@example.test', 'password' => 'incorrect'])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'limited@example.test', 'password' => 'incorrect'])->assertTooManyRequests();
});

it('blocks simulated API deposits when demo mode is disabled', function () {
    config(['demo.enabled' => false]);
    $user = User::factory()->create();
    $wallet = $this->actingAs($user)->postJson('/api/v1/wallets', ['currency' => 'EUR'])->assertCreated()->json('data.id');

    $this->actingAs($user)->postJson('/api/v1/transactions/deposits', [
        'wallet_id' => $wallet,
        'amount' => '100.00',
        'reference' => 'blocked-deposit',
    ], ['Idempotency-Key' => 'blocked-deposit-key'])->assertForbidden();

    $this->assertDatabaseCount('transactions', 0);
    $this->assertDatabaseCount('idempotency_keys', 0);
});

it('validates currency precision before transaction processing', function () {
    $user = User::factory()->create();
    $wallet = $this->actingAs($user)->postJson('/api/v1/wallets', ['currency' => 'EUR'])->assertCreated()->json('data.id');

    $this->actingAs($user)->postJson('/api/v1/transactions/deposits', [
        'wallet_id' => $wallet,
        'amount' => '100.001',
        'reference' => 'invalid-precision',
    ], ['Idempotency-Key' => 'invalid-precision-key'])->assertUnprocessable()->assertJsonValidationErrors('amount');

    $this->assertDatabaseCount('transactions', 0);
});

it('redacts secrets recursively from audit records', function () {
    $audit = app(AuditService::class)->record(null, 'security.test', 'test', 'one', null, [
        'email' => 'reviewer@example.test',
        'password' => 'plain-text-secret',
        'nested' => ['token' => 'bearer-secret'],
    ]);

    expect($audit->after_state)->toMatchArray([
        'email' => 'reviewer@example.test',
        'password' => '[REDACTED]',
        'nested' => ['token' => '[REDACTED]'],
    ]);
});
