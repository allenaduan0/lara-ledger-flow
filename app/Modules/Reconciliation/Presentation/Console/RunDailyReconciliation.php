<?php

namespace App\Modules\Reconciliation\Presentation\Console;

use App\Modules\Reconciliation\Application\Actions\RunDailyReconciliationAction;
use Illuminate\Console\Command;

class RunDailyReconciliation extends Command
{
    protected $signature = 'reconciliation:run {date? : UTC business date in YYYY-MM-DD format}';

    protected $description = 'Generate daily ledger reconciliation reports';

    public function handle(RunDailyReconciliationAction $action): int
    {
        $date = $this->argument('date') ?: now('UTC')->subDay()->toDateString();
        $reports = $action->execute($date);
        $this->table(['Currency', 'Expected', 'Ledger', 'Difference', 'Status'], $reports->map(fn ($report) => [$report->currency_code, $report->expected_minor, $report->ledger_minor, $report->difference_minor, $report->status]));

        return self::SUCCESS;
    }
}
