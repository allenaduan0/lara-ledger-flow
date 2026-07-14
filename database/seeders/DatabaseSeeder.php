<?php

namespace Database\Seeders;

use App\Modules\Identity\Domain\Authorization\PermissionName;
use App\Modules\Identity\Domain\Authorization\RoleName;
use App\Modules\Identity\Infrastructure\Persistence\Models\Permission;
use App\Modules\Identity\Infrastructure\Persistence\Models\Role;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(CurrencySeeder::class);

        $permissions = collect(PermissionName::cases())->mapWithKeys(fn (PermissionName $permission) => [
            $permission->value => Permission::query()->firstOrCreate(['name' => $permission->value], ['description' => $permission->description()]),
        ]);
        $admin = Role::query()->firstOrCreate(['name' => RoleName::Administrator->value]);
        $operator = Role::query()->firstOrCreate(['name' => RoleName::Operator->value]);
        $customer = Role::query()->firstOrCreate(['name' => RoleName::Customer->value]);
        $admin->permissions()->sync($permissions->pluck('id'));
        $operator->permissions()->sync($permissions->only(['users.view', 'reports.view', 'reconciliation.view'])->pluck('id'));
        $customer->permissions()->sync([]);
        if (app()->environment('local') || config('demo.enabled')) {
            $user = User::query()->firstOrCreate(['email' => config('demo.admin_email')], ['name' => 'LedgerFlow Admin', 'password' => config('demo.admin_password')]);
            $user->roles()->syncWithoutDetaching([$admin->id]);
            $demoCustomer = User::query()->firstOrCreate(['email' => config('demo.customer_email')], ['name' => 'Demo Customer', 'password' => config('demo.customer_password')]);
            $demoCustomer->roles()->syncWithoutDetaching([$customer->id]);
        }
    }
}
