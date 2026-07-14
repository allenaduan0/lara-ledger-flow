<?php

namespace App\Modules\Wallet\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Wallet\Application\Actions\CreateWalletAction;
use App\Modules\Wallet\Application\Actions\FreezeWalletAction;
use App\Modules\Wallet\Application\Actions\UnfreezeWalletAction;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;
use App\Modules\Wallet\Presentation\Http\Requests\CreateWalletRequest;
use App\Modules\Wallet\Presentation\Http\Requests\FreezeWalletRequest;
use App\Modules\Wallet\Presentation\Http\Resources\WalletResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WalletController extends Controller
{
    public function store(CreateWalletRequest $request, CreateWalletAction $action): JsonResponse
    {
        Gate::authorize('create', Wallet::class);
        /** @var User $user */
        $user = $request->user();

        return (new WalletResource($action->execute($user, $request->validated('currency'), $request->validated('name'))))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Wallet $wallet): WalletResource
    {
        Gate::authorize('view', $wallet);

        return new WalletResource($wallet->load(['currency', 'limit']));
    }

    public function freeze(FreezeWalletRequest $request, Wallet $wallet, FreezeWalletAction $action): WalletResource
    {
        Gate::authorize('freeze', $wallet);
        /** @var User $user */
        $user = $request->user();

        return new WalletResource($action->execute($wallet, $user, $request->validated('reason'))->load(['currency', 'limit']));
    }

    public function unfreeze(Request $request, Wallet $wallet, UnfreezeWalletAction $action): WalletResource
    {
        Gate::authorize('unfreeze', $wallet);

        return new WalletResource($action->execute($wallet)->load(['currency', 'limit']));
    }
}
