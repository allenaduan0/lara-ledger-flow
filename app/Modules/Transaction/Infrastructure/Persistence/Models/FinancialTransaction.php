<?php

namespace App\Modules\Transaction\Infrastructure\Persistence\Models;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Ledger\Infrastructure\Persistence\Models\LedgerTransaction;
use App\Modules\Transaction\Domain\Enums\TransactionStatus;
use App\Modules\Transaction\Domain\Enums\TransactionType;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Currency;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialTransaction extends Model
{
    use HasUlids;

    protected $table = 'transactions';

    protected $fillable = [
        'initiated_by', 'type', 'status', 'source_wallet_id', 'destination_wallet_id', 'currency_code',
        'amount_minor', 'reference', 'description', 'ledger_transaction_id', 'refunded_transaction_id',
        'failure_code', 'failure_message', 'completed_at', 'failed_at', 'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class, 'status' => TransactionStatus::class, 'amount_minor' => 'integer',
            'completed_at' => 'immutable_datetime', 'failed_at' => 'immutable_datetime', 'reversed_at' => 'immutable_datetime',
        ];
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function sourceWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'source_wallet_id');
    }

    public function destinationWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'destination_wallet_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class);
    }

    public function refundedTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'refunded_transaction_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(TransactionStatusHistory::class, 'transaction_id');
    }
}
