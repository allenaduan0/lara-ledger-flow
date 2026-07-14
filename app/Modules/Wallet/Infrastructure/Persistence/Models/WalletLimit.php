<?php

namespace App\Modules\Wallet\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletLimit extends Model
{
    protected $fillable = ['per_transaction_minor', 'daily_outgoing_minor', 'monthly_outgoing_minor'];

    protected function casts(): array
    {
        return ['per_transaction_minor' => 'integer', 'daily_outgoing_minor' => 'integer', 'monthly_outgoing_minor' => 'integer'];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
