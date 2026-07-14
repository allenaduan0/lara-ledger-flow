<?php

namespace App\Modules\Ledger\Infrastructure\Persistence\Models;

use App\Modules\Ledger\Domain\Enums\EntryDirection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class LedgerEntry extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = ['ledger_transaction_id', 'account_id', 'direction', 'amount_minor', 'created_at'];

    protected function casts(): array
    {
        return ['direction' => EntryDirection::class, 'amount_minor' => 'integer', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Ledger entries are immutable.'));
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'ledger_transaction_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
