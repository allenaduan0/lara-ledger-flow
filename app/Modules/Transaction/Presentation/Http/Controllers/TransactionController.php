<?php

namespace App\Modules\Transaction\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Transaction\Application\Actions\CreateDepositAction;
use App\Modules\Transaction\Application\Actions\CreateRefundAction;
use App\Modules\Transaction\Application\Actions\CreateTransferAction;
use App\Modules\Transaction\Application\Actions\CreateWithdrawalAction;
use App\Modules\Transaction\Infrastructure\Persistence\Models\FinancialTransaction;
use App\Modules\Transaction\Presentation\Http\Requests\RefundRequest;
use App\Modules\Transaction\Presentation\Http\Requests\TransferRequest;
use App\Modules\Transaction\Presentation\Http\Requests\WalletMovementRequest;
use App\Modules\Transaction\Presentation\Http\Resources\TransactionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TransactionController extends Controller
{
    public function transfer(TransferRequest $request, CreateTransferAction $action): JsonResponse
    {
        return $this->created($action->execute($this->user($request), $request->validated()));
    }

    public function deposit(WalletMovementRequest $request, CreateDepositAction $action): JsonResponse
    {
        return $this->created($action->execute($this->user($request), $request->validated()));
    }

    public function withdraw(WalletMovementRequest $request, CreateWithdrawalAction $action): JsonResponse
    {
        return $this->created($action->execute($this->user($request), $request->validated()));
    }

    public function refund(RefundRequest $request, FinancialTransaction $transaction, CreateRefundAction $action): JsonResponse
    {
        Gate::authorize('refund', $transaction);

        return $this->created($action->execute($this->user($request), $transaction, $request->validated()));
    }

    public function show(FinancialTransaction $transaction): TransactionResource
    {
        Gate::authorize('view', $transaction);

        return new TransactionResource($transaction);
    }

    private function created(FinancialTransaction $transaction): JsonResponse
    {
        return (new TransactionResource($transaction))->response()->setStatusCode(201);
    }

    private function user(Request $request): User
    { /** @var User $user */ $user = $request->user();

        return $user;
    }
}
