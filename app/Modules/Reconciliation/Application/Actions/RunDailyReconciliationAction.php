<?php

namespace App\Modules\Reconciliation\Application\Actions;

use App\Modules\Reconciliation\Application\Services\DailyReconciliationService;
use Illuminate\Support\Collection;

final class RunDailyReconciliationAction
{
    public function __construct(private readonly DailyReconciliationService $service) {}

    public function execute(string $date, ?int $actorId = null): Collection
    {
        return $this->service->run($date, $actorId);
    }
}
