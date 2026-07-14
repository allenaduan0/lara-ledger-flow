<?php

namespace App\Modules\Transaction\Presentation\Http\Middleware;

use App\Modules\Audit\Application\Services\AuditService;
use App\Modules\Transaction\Application\Services\IdempotencyService;
use App\Modules\Transaction\Domain\Enums\IdempotencyStatus;
use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class RequireIdempotencyKey
{
    public function __construct(
        private readonly IdempotencyService $idempotency,
        private readonly AuditService $audit,
        private readonly ExceptionHandler $exceptions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header('Idempotency-Key'));
        if ($key === '') {
            return response()->json(['message' => 'The Idempotency-Key header is required.', 'code' => 'IDEMPOTENCY_KEY_REQUIRED'], 400);
        }
        if (strlen($key) > 128 || ! preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $key)) {
            return response()->json(['message' => 'The Idempotency-Key header format is invalid.', 'code' => 'INVALID_IDEMPOTENCY_KEY'], 400);
        }

        $hash = $this->idempotency->fingerprint($request);
        $record = $this->idempotency->claim($request, $key, $hash);

        if (! $record->wasRecentlyCreated) {
            if (! hash_equals($record->request_hash, $hash)) {
                return response()->json(['message' => 'This idempotency key was used with a different request.', 'code' => 'IDEMPOTENCY_KEY_REUSED'], 409);
            }

            $record = $this->idempotency->recoverIfStale($record, $request);
            if (! $record) {
                $record = $this->idempotency->claim($request, $key, $hash);
            } elseif ($record->status !== IdempotencyStatus::Processing) {
                return $this->idempotency->replay($record);
            } else {
                return response()->json(['message' => 'An identical request is still processing.', 'code' => 'REQUEST_IN_PROGRESS'], 409, ['Retry-After' => '2']);
            }
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->exceptions->report($exception);
            $response = $this->exceptions->render($request, $exception);
            $this->audit->record($request->user()?->getKey(), 'financial_request.exception', 'idempotency_key', $record->id, null, ['status' => 'failed'], [
                'exception' => $exception::class,
                'request_path' => '/'.$request->path(),
            ]);
        }

        $this->idempotency->complete($record, $response);

        if ($response->getStatusCode() >= 400) {
            $this->audit->record($request->user()?->getKey(), 'financial_request.failed', 'idempotency_key', $record->id, null, [
                'status' => 'failed',
                'response_status' => $response->getStatusCode(),
            ], ['request_path' => '/'.$request->path()]);
        }

        return $response;
    }
}
