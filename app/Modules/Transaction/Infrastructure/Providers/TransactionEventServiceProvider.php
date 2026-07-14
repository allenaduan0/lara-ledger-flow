<?php

namespace App\Modules\Transaction\Infrastructure\Providers;

use App\Modules\Audit\Application\Listeners\CreateAuditEntry;
use App\Modules\Notification\Application\Listeners\SendTransactionNotification;
use App\Modules\Reporting\Application\Listeners\GenerateStatement;
use App\Modules\Reporting\Application\Listeners\UpdateReportingData;
use App\Modules\Transaction\Domain\Events\TransactionCreated;
use App\Modules\Transaction\Domain\Events\TransferCompleted;
use App\Modules\Transaction\Domain\Events\TransferFailed;
use App\Modules\Transaction\Domain\Events\WalletCredited;
use App\Modules\Transaction\Domain\Events\WalletDebited;
use Illuminate\Foundation\Support\Providers\EventServiceProvider;

class TransactionEventServiceProvider extends EventServiceProvider
{
    protected $listen = [
        TransactionCreated::class => [SendTransactionNotification::class, UpdateReportingData::class, CreateAuditEntry::class],
        TransferCompleted::class => [SendTransactionNotification::class, UpdateReportingData::class, CreateAuditEntry::class],
        TransferFailed::class => [SendTransactionNotification::class, UpdateReportingData::class, CreateAuditEntry::class],
        WalletCredited::class => [SendTransactionNotification::class, GenerateStatement::class, CreateAuditEntry::class],
        WalletDebited::class => [SendTransactionNotification::class, GenerateStatement::class, CreateAuditEntry::class],
    ];
}
