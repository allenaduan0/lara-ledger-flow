<?php

use App\Modules\Identity\Domain\Authorization\RoleName;
use App\Modules\Identity\Infrastructure\Persistence\Models\Role;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Currency;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['demo.enabled' => true]);

    Currency::query()->create([
        'code' => 'USD',
        'name' => 'US Dollar',
        'minor_unit' => 2,
        'is_active' => true,
    ]);

    foreach (RoleName::cases() as $role) {
        Role::query()->create(['name' => $role->value]);
    }
});

function portalUser(RoleName $role = RoleName::Customer): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('name', $role->value)->firstOrFail());

    return $user;
}

it('shows demo credentials only while demo mode is enabled', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee(config('demo.admin_email'))
        ->assertSee(config('demo.customer_email'));

    config(['demo.enabled' => false]);

    $this->get('/login')
        ->assertOk()
        ->assertDontSee(config('demo.admin_email'))
        ->assertDontSee(config('demo.customer_email'));
});

it('routes customers and administrators to their respective dashboards after login', function () {
    $customer = portalUser();
    $admin = portalUser(RoleName::Administrator);

    $this->post('/login', ['email' => $customer->email, 'password' => 'password'])
        ->assertRedirect(route('customer.dashboard'));

    $this->post('/app/logout')->assertRedirect(route('login'));

    $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect(route('admin.dashboard'));
});

it('lets a customer create a wallet and complete a demo deposit through the portal', function () {
    $customer = portalUser();

    $this->actingAs($customer)->post('/app/wallets', [
        'currency' => 'USD',
        'name' => 'Everyday wallet',
    ])->assertRedirect();

    $wallet = Wallet::query()->whereBelongsTo($customer)->firstOrFail();

    $this->actingAs($customer)->post('/app/transactions/deposits', [
        'wallet_id' => $wallet->id,
        'amount' => '125.50',
        'reference' => 'portal-deposit-001',
        'description' => 'Portfolio demo funding',
    ])->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'initiated_by' => $customer->id,
        'reference' => 'portal-deposit-001',
        'status' => 'completed',
        'amount_minor' => 12550,
    ]);

    $this->actingAs($customer)->get("/app/wallets/{$wallet->id}")
        ->assertOk()
        ->assertSee('125.50');
});

it('prevents customers from viewing another customers wallet', function () {
    $owner = portalUser();
    $intruder = portalUser();

    $this->actingAs($owner)->post('/app/wallets', [
        'currency' => 'USD',
        'name' => 'Private wallet',
    ]);

    $wallet = Wallet::query()->whereBelongsTo($owner)->firstOrFail();

    $this->actingAs($intruder)->get("/app/wallets/{$wallet->id}")->assertForbidden();
});

it('disables customer-created deposits outside demo mode', function () {
    config(['demo.enabled' => false]);
    $customer = portalUser();

    $this->actingAs($customer)->get('/app/transactions/new/deposit')->assertForbidden();
});
