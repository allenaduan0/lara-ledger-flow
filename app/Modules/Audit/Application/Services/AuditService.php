<?php

namespace App\Modules\Audit\Application\Services;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;

final class AuditService
{
    private const SENSITIVE_KEYS = ['password', 'password_confirmation', 'token', 'access_token', 'authorization', 'cookie', 'secret', 'api_key'];

    public function record(?int $actorId, string $action, string $subjectType, string $subjectId, ?array $before = null, ?array $after = null, array $metadata = []): AuditLog
    {
        return AuditLog::query()->create([
            'actor_id' => $actorId, 'action' => $action, 'subject_type' => $subjectType, 'subject_id' => $subjectId,
            'before_state' => $this->redact($before), 'after_state' => $this->redact($after), 'metadata' => $this->redact($metadata), 'created_at' => now(),
        ]);
    }

    private function redact(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }
}
