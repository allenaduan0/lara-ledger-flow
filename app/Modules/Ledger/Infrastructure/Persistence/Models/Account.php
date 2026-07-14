<?php

namespace App\Modules\Ledger\Infrastructure\Persistence\Models;

use App\Modules\Ledger\Domain\Enums\AccountType;
use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasUlids;

    protected $fillable = ['wallet_id', 'code', 'name', 'type', 'normal_balance', 'currency_code', 'status'];

    protected function casts(): array
    {
        return ['type' => AccountType::class, 'normal_balance' => EntryDirection::class];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
