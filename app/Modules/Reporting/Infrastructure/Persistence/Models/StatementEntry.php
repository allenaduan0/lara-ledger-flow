<?php

namespace App\Modules\Reporting\Infrastructure\Persistence\Models;

use App\Modules\Ledger\Domain\Enums\EntryDirection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class StatementEntry extends Model
{
    use HasUlids;

    protected $fillable = ['event_id', 'wallet_id', 'transaction_id', 'ledger_entry_id', 'direction', 'amount_minor', 'currency_code', 'occurred_at'];

    protected function casts(): array
    {
        return ['direction' => EntryDirection::class, 'amount_minor' => 'integer', 'occurred_at' => 'immutable_datetime'];
    }
}
