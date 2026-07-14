<?php

namespace App\Modules\Transaction\Infrastructure\Queue;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use Illuminate\Support\Facades\Log;
use Throwable;

trait QueuedFinancialListener
{
    public string $connection = 'database';

    public int $tries = 3;

    public int $maxExceptions = 3;

    public function backoff(): array
    {
        return [5, 30, 120];
    }

    public function failed(object $event, Throwable $exception): void
    {
        $eventId = $event->eventId ?? 'unknown';
        Log::error('Queued financial event listener failed permanently.', [
            'listener' => static::class,
            'event_id' => $eventId,
            'exception' => $exception::class,
        ]);

        AuditLog::query()->firstOrCreate(
            ['event_id' => 'listener-failed:'.hash('sha256', static::class.':'.$eventId)],
            ['actor_id' => null, 'action' => 'event_listener.failed', 'subject_type' => static::class, 'subject_id' => (string) $eventId, 'after_state' => ['status' => 'failed'], 'metadata' => ['exception' => $exception::class], 'created_at' => now()],
        );
    }
}
