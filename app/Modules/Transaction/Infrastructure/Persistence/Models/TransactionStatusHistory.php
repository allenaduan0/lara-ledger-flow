<?php

namespace App\Modules\Transaction\Infrastructure\Persistence\Models;

use App\Modules\Transaction\Domain\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'transaction_status_history';

    protected $fillable = ['transaction_id', 'from_status', 'to_status', 'actor_id', 'reason', 'created_at'];

    protected function casts(): array
    {
        return ['from_status' => TransactionStatus::class, 'to_status' => TransactionStatus::class, 'created_at' => 'immutable_datetime'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'transaction_id');
    }
}
