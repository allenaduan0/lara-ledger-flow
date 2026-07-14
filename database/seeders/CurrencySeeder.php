<?php

namespace Database\Seeders;

use App\Modules\Wallet\Infrastructure\Persistence\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        Currency::query()->upsert([
            ['code' => 'AED', 'name' => 'UAE Dirham', 'minor_unit' => 2, 'is_active' => true],
            ['code' => 'EUR', 'name' => 'Euro', 'minor_unit' => 2, 'is_active' => true],
            ['code' => 'GBP', 'name' => 'Pound Sterling', 'minor_unit' => 2, 'is_active' => true],
            ['code' => 'USD', 'name' => 'US Dollar', 'minor_unit' => 2, 'is_active' => true],
        ], ['code'], ['name', 'minor_unit', 'is_active']);
    }
}
