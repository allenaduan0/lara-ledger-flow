<?php

namespace App\Modules\Reconciliation\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ReconciliationDiscrepancy extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = ['reconciliation_report_id', 'type', 'reference', 'expected_minor', 'actual_minor', 'difference_minor', 'details', 'created_at'];

    protected function casts(): array
    {
        return ['expected_minor' => 'integer', 'actual_minor' => 'integer', 'difference_minor' => 'integer', 'details' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
