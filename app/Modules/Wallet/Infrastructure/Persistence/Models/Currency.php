<?php

namespace App\Modules\Wallet\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['code', 'name', 'minor_unit', 'is_active'];

    protected function casts(): array
    {
        return ['minor_unit' => 'integer', 'is_active' => 'boolean'];
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class, 'currency_code', 'code');
    }

    public function formatMinor(int $minor): string
    {
        return $this->code.' '.number_format($minor / (10 ** $this->minor_unit), $this->minor_unit, '.', ',');
    }
}
