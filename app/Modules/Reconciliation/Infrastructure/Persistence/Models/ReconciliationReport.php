<?php

namespace App\Modules\Reconciliation\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReconciliationReport extends Model
{
    use HasUlids;

    protected $fillable = ['business_date', 'currency_code', 'expected_minor', 'ledger_minor', 'difference_minor', 'journal_count', 'invalid_journal_count', 'missing_journal_count', 'status', 'generated_by', 'generated_at'];

    protected function casts(): array
    {
        return ['business_date' => 'immutable_date', 'expected_minor' => 'integer', 'ledger_minor' => 'integer', 'difference_minor' => 'integer', 'generated_at' => 'immutable_datetime'];
    }

    public function discrepancies(): HasMany
    {
        return $this->hasMany(ReconciliationDiscrepancy::class);
    }
}
