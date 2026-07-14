<?php

namespace App\Modules\Audit\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = ['event_id', 'actor_id', 'action', 'subject_type', 'subject_id', 'before_state', 'after_state', 'metadata', 'created_at'];

    protected function casts(): array
    {
        return ['before_state' => 'array', 'after_state' => 'array', 'metadata' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
