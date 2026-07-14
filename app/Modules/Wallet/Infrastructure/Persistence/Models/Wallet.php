<?php

namespace App\Modules\Wallet\Infrastructure\Persistence\Models;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Ledger\Infrastructure\Persistence\Models\Account;
use App\Modules\Wallet\Domain\Enums\WalletStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Wallet extends Model
{
    use HasUlids;

    protected $fillable = ['user_id', 'currency_code', 'name', 'status'];

    protected function casts(): array
    {
        return ['status' => WalletStatus::class, 'frozen_at' => 'immutable_datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function limit(): HasOne
    {
        return $this->hasOne(WalletLimit::class);
    }

    public function account(): HasOne
    {
        return $this->hasOne(Account::class);
    }
}
