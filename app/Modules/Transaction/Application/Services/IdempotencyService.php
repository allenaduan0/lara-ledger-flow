<?php

namespace App\Modules\Transaction\Application\Services;

use App\Modules\Transaction\Domain\Enums\IdempotencyStatus;
use App\Modules\Transaction\Infrastructure\Persistence\Models\FinancialTransaction;
use App\Modules\Transaction\Infrastructure\Persistence\Models\IdempotencyKey;
use App\Modules\Transaction\Presentation\Http\Resources\TransactionResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class IdempotencyService
{
    public function fingerprint(Request $request): string
    {
        $body = $this->canonicalize($request->all());

        return hash('sha256', json_encode([
            'method' => strtoupper($request->method()),
            'path' => '/'.$request->path(),
            'body' => $body,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public function claim(Request $request, string $key, string $hash): IdempotencyKey
    {
        try {
            return IdempotencyKey::query()->create([
                'user_id' => $request->user()->getKey(),
                'key' => $key,
                'request_hash' => $hash,
                'request_method' => strtoupper($request->method()),
                'request_path' => '/'.$request->path(),
                'status' => IdempotencyStatus::Processing,
                'locked_until' => now()->addMinutes(5),
            ]);
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            return IdempotencyKey::query()->where('user_id', $request->user()->getKey())->where('key', $key)->firstOrFail();
        }
    }

    public function recoverIfStale(IdempotencyKey $record, Request $request): ?IdempotencyKey
    {
        if ($record->status !== IdempotencyStatus::Processing || $record->locked_until?->isFuture()) {
            return $record;
        }

        return DB::transaction(function () use ($record, $request): ?IdempotencyKey {
            $record = IdempotencyKey::query()->lockForUpdate()->findOrFail($record->id);
            if ($record->status !== IdempotencyStatus::Processing || $record->locked_until?->isFuture()) {
                return $record;
            }

            $reference = $request->input('reference');
            $transaction = is_string($reference)
                ? FinancialTransaction::query()->where('initiated_by', $record->user_id)->where('reference', $reference)->first()
                : null;

            if ($transaction) {
                $failed = $transaction->status->value === 'failed';
                $payload = $failed
                    ? ['message' => $transaction->failure_message, 'code' => 'TRANSACTION_FAILED', 'transaction_id' => $transaction->id]
                    : (new TransactionResource($transaction))->resolve($request);
                if (! $failed) {
                    $payload = ['data' => $payload];
                }

                $record->forceFill([
                    'status' => $failed ? IdempotencyStatus::Failed : IdempotencyStatus::Completed,
                    'response_status' => $failed ? 422 : 201,
                    'response' => $payload,
                    'transaction_id' => $transaction->id,
                    'completed_at' => now(),
                    'locked_until' => null,
                ])->save();

                return $record->refresh();
            }

            $record->delete();

            return null;
        });
    }

    public function complete(IdempotencyKey $record, Response $response): void
    {
        $body = json_decode((string) $response->getContent(), true);
        if (! is_array($body)) {
            $body = ['raw' => (string) $response->getContent()];
        }

        $transactionId = data_get($body, 'data.id') ?? data_get($body, 'transaction_id');
        $record->forceFill([
            'status' => $response->getStatusCode() >= 400 ? IdempotencyStatus::Failed : IdempotencyStatus::Completed,
            'response_status' => $response->getStatusCode(),
            'response' => $body,
            'transaction_id' => $transactionId,
            'completed_at' => now(),
            'locked_until' => null,
        ])->save();
    }

    public function replay(IdempotencyKey $record): Response
    {
        return response()->json($record->response, $record->response_status ?? 500, ['Idempotent-Replayed' => 'true']);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(fn (mixed $item) => $this->canonicalize($item), $value);
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? ''), ['23000', '23505'], true);
    }
}
