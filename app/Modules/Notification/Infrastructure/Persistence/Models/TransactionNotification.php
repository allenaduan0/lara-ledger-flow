<?php

namespace App\Modules\Notification\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class TransactionNotification extends Model
{
    use HasUlids;

    protected $fillable = ['event_id', 'user_id', 'transaction_id', 'type', 'payload', 'delivered_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'delivered_at' => 'immutable_datetime'];
    }
}
