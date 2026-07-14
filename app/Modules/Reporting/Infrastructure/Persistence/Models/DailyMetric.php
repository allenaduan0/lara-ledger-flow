<?php

namespace App\Modules\Reporting\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

class DailyMetric extends Model
{
    protected $table = 'reporting_daily_metrics';

    protected $fillable = ['business_date', 'currency_code', 'transactions_created', 'transfers_completed', 'transfers_failed', 'transferred_minor'];

    protected function casts(): array
    {
        return ['business_date' => 'immutable_date', 'transactions_created' => 'integer', 'transfers_completed' => 'integer', 'transfers_failed' => 'integer', 'transferred_minor' => 'integer'];
    }
}
