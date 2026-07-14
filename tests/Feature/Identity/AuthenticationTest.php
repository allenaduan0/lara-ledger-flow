<?php

use App\Modules\Identity\Domain\Authorization\RoleName;
use App\Modules\Identity\Infrastructure\Persistence\Models\Role;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
beforeEach(fn () => Role::query()->create(['name' => RoleName::Customer->value]));
it('registers a customer and issues a token', function () {
    $this->postJson('/api/v1/auth/register', ['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'password' => 'SecurePassword123', 'password_confirmation' => 'SecurePassword123', 'device_name' => 'tests'])
        ->assertCreated()->assertJsonPath('data.user.email', 'ada@example.com')->assertJsonStructure(['data' => ['token']]);
    expect(User::query()->firstOrFail()->hasRole(RoleName::Customer->value))->toBeTrue();
});
it('authenticates and revokes the current token', function () {
    $user = User::factory()->create(['password' => 'SecurePassword123']);
    $token = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'SecurePassword123'])->assertOk()->json('data.token');
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.email', $user->email);
    $this->withToken($token)->deleteJson('/api/v1/auth/tokens/current')->assertNoContent();
    $this->assertDatabaseCount('personal_access_tokens', 0);
});
it('rejects invalid credentials', function () {
    User::factory()->create(['email' => 'ada@example.com']);
    $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('email');
});
