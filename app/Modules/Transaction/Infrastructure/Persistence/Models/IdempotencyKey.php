<?php

namespace App\Modules\Transaction\Infrastructure\Persistence\Models;

use App\Modules\Transaction\Domain\Enums\IdempotencyStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdempotencyKey extends Model
{
    use HasUlids;

    protected $fillable = ['user_id', 'key', 'request_hash', 'request_method', 'request_path', 'status', 'response_status', 'response', 'transaction_id', 'locked_until', 'completed_at'];

    protected function casts(): array
    {
        return ['status' => IdempotencyStatus::class, 'response' => 'array', 'locked_until' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'transaction_id');
    }
}
