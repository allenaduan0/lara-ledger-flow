<?php

namespace App\Modules\Wallet\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Transaction\Application\Actions\CreateDepositAction;
use App\Modules\Transaction\Application\Actions\CreateRefundAction;
use App\Modules\Transaction\Application\Actions\CreateTransferAction;
use App\Modules\Transaction\Application\Actions\CreateWithdrawalAction;
use App\Modules\Transaction\Domain\Exceptions\TransactionProcessingException;
use App\Modules\Transaction\Infrastructure\Persistence\Models\FinancialTransaction;
use App\Modules\Transaction\Presentation\Http\Requests\RefundRequest;
use App\Modules\Transaction\Presentation\Http\Requests\TransferRequest;
use App\Modules\Transaction\Presentation\Http\Requests\WalletMovementRequest;
use App\Modules\Wallet\Application\Actions\CreateWalletAction;
use App\Modules\Wallet\Application\Actions\FreezeWalletAction;
use App\Modules\Wallet\Application\Actions\UnfreezeWalletAction;
use App\Modules\Wallet\Domain\Contracts\WalletBalanceReader;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Currency;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;
use App\Modules\Wallet\Presentation\Http\Requests\CreateWalletRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomerPortalController extends Controller
{
    public function dashboard(Request $request, WalletBalanceReader $balances): View
    {
        $wallets = $this->user($request)->wallets()->with('currency')->get()->map(function (Wallet $wallet) use ($balances): Wallet {
            $wallet->setAttribute('ledger_balance', $balances->forWallet($wallet->id, $wallet->currency_code));

            return $wallet;
        });

        return view('customer.dashboard', [
            'wallets' => $wallets,
            'recentTransactions' => FinancialTransaction::query()->with('currency')->where('initiated_by', $request->user()->getKey())->latest()->limit(8)->get(),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function createWallet(CreateWalletRequest $request, CreateWalletAction $action): RedirectResponse
    {
        $wallet = $action->execute($this->user($request), $request->validated('currency'), $request->validated('name'));

        return redirect()->route('customer.wallets.show', $wallet)->with('status', 'Wallet created.');
    }

    public function wallet(Request $request, Wallet $wallet, WalletBalanceReader $balances): View
    {
        Gate::authorize('view', $wallet);

        return view('customer.wallets.show', ['wallet' => $wallet->load('currency'), 'balance' => $balances->forWallet($wallet->id, $wallet->currency_code)]);
    }

    public function freeze(Request $request, Wallet $wallet, FreezeWalletAction $action): RedirectResponse
    {
        Gate::authorize('freeze', $wallet);
        $action->execute($wallet, $this->user($request), 'Customer requested from portal');

        return back()->with('status', 'Wallet frozen.');
    }

    public function unfreeze(Request $request, Wallet $wallet, UnfreezeWalletAction $action): RedirectResponse
    {
        Gate::authorize('unfreeze', $wallet);
        $action->execute($wallet);

        return back()->with('status', 'Wallet unfrozen.');
    }

    public function transactions(Request $request): View
    {
        return view('customer.transactions.index', ['transactions' => FinancialTransaction::query()->with('currency')->where('initiated_by', $request->user()->getKey())->latest()->paginate(20)]);
    }

    public function transaction(FinancialTransaction $transaction): View
    {
        Gate::authorize('view', $transaction);

        return view('customer.transactions.show', ['transaction' => $transaction->load(['currency', 'statusHistory'])]);
    }

    public function movementForm(Request $request, string $type): View
    {
        abort_unless(in_array($type, ['transfer', 'deposit', 'withdrawal'], true), 404);
        if ($type === 'deposit') {
            abort_unless(config('demo.enabled'), 403, 'Demo deposits are disabled.');
        }

        return view('customer.transactions.create', ['type' => $type, 'wallets' => $this->user($request)->wallets()->with('currency')->get(), 'reference' => 'web-'.Str::lower((string) Str::ulid())]);
    }

    public function transfer(TransferRequest $request, CreateTransferAction $action): RedirectResponse
    {
        return $this->process(fn () => $action->execute($this->user($request), $request->validated()));
    }

    public function deposit(WalletMovementRequest $request, CreateDepositAction $action): RedirectResponse
    {
        abort_unless(config('demo.enabled'), 403, 'Demo deposits are disabled.');

        return $this->process(fn () => $action->execute($this->user($request), $request->validated()));
    }

    public function withdraw(WalletMovementRequest $request, CreateWithdrawalAction $action): RedirectResponse
    {
        return $this->process(fn () => $action->execute($this->user($request), $request->validated()));
    }

    public function refund(RefundRequest $request, FinancialTransaction $transaction, CreateRefundAction $action): RedirectResponse
    {
        Gate::authorize('refund', $transaction);

        return $this->process(fn () => $action->execute($this->user($request), $transaction, $request->validated()));
    }

    private function process(callable $operation): RedirectResponse
    {
        try {
            $transaction = $operation();

            return redirect()->route('customer.transactions.show', $transaction)->with('status', 'Transaction completed.');
        } catch (TransactionProcessingException $exception) {
            return redirect()->route('customer.transactions.show', $exception->transactionId)->with('error', $exception->getMessage());
        }
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
