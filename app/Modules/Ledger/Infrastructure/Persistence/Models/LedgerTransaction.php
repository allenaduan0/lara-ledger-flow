<?php

namespace App\Modules\Ledger\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerTransaction extends Model
{
    use HasUlids;

    protected $fillable = ['reference', 'description', 'posted_at'];

    protected function casts(): array
    {
        return ['posted_at' => 'immutable_datetime'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
