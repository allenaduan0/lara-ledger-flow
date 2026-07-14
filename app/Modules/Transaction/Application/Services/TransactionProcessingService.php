<?php

namespace App\Modules\Transaction\Application\Services;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Ledger\Application\Services\BalanceCalculationService;
use App\Modules\Ledger\Application\Services\LedgerPostingService;
use App\Modules\Ledger\Domain\Data\PostingLine;
use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\Ledger\Domain\ValueObjects\Money;
use App\Modules\Ledger\Infrastructure\Persistence\Models\Account;
use App\Modules\Ledger\Infrastructure\Persistence\Models\LedgerTransaction;
use App\Modules\Transaction\Domain\Enums\TransactionStatus;
use App\Modules\Transaction\Domain\Enums\TransactionType;
use App\Modules\Transaction\Domain\Events\TransactionCompleted;
use App\Modules\Transaction\Domain\Events\TransactionCreated;
use App\Modules\Transaction\Domain\Events\TransferCompleted;
use App\Modules\Transaction\Domain\Events\TransferFailed;
use App\Modules\Transaction\Domain\Events\WalletCredited;
use App\Modules\Transaction\Domain\Events\WalletDebited;
use App\Modules\Transaction\Domain\Exceptions\InsufficientFundsException;
use App\Modules\Transaction\Domain\Exceptions\TransactionProcessingException;
use App\Modules\Transaction\Infrastructure\Persistence\Models\FinancialTransaction;
use App\Modules\Wallet\Application\Services\WalletOperationGuard;
use App\Modules\Wallet\Domain\Exceptions\FrozenWalletException;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Currency;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TransactionProcessingService
{
    public function __construct(
        private readonly LedgerPostingService $ledger,
        private readonly BalanceCalculationService $balances,
        private readonly TransactionLifecycleService $lifecycle,
        private readonly WalletOperationGuard $walletGuard,
        private readonly SystemAccountService $systemAccounts,
    ) {}

    public function transfer(User $actor, string $sourceWalletId, string $destinationWalletId, string $decimalAmount, string $reference, ?string $description): FinancialTransaction
    {
        if ($sourceWalletId === $destinationWalletId) {
            throw new DomainException('Source and destination wallets must differ.');
        }

        return $this->process(TransactionType::Transfer, $actor, $sourceWalletId, $destinationWalletId, $decimalAmount, $reference, $description,
            fn (Wallet $source, Wallet $destination, Money $money) => [
                new PostingLine($source->account->id, EntryDirection::Debit, $money),
                new PostingLine($destination->account->id, EntryDirection::Credit, $money),
            ]);
    }

    public function deposit(User $actor, string $walletId, string $decimalAmount, string $reference, ?string $description): FinancialTransaction
    {
        return $this->process(TransactionType::Deposit, $actor, null, $walletId, $decimalAmount, $reference, $description,
            function (?Wallet $source, Wallet $destination, Money $money): array {
                $settlement = $this->systemAccounts->settlement($money->currency);

                return [new PostingLine($settlement->id, EntryDirection::Debit, $money), new PostingLine($destination->account->id, EntryDirection::Credit, $money)];
            });
    }

    public function withdraw(User $actor, string $walletId, string $decimalAmount, string $reference, ?string $description): FinancialTransaction
    {
        return $this->process(TransactionType::Withdrawal, $actor, $walletId, null, $decimalAmount, $reference, $description,
            function (Wallet $source, ?Wallet $destination, Money $money): array {
                $settlement = $this->systemAccounts->settlement($money->currency);

                return [new PostingLine($source->account->id, EntryDirection::Debit, $money), new PostingLine($settlement->id, EntryDirection::Credit, $money)];
            });
    }

    public function refund(User $actor, FinancialTransaction $original, string $reference, ?string $description): FinancialTransaction
    {
        $result = DB::transaction(function () use ($actor, $original, $reference, $description): array {
            $original = FinancialTransaction::query()->lockForUpdate()->findOrFail($original->id);
            if ($original->initiated_by !== $actor->getKey()) {
                throw new AuthorizationException;
            }
            if ($original->status !== TransactionStatus::Completed || ! $original->ledger_transaction_id) {
                throw new DomainException('Only completed, non-refunded transactions can be refunded.');
            }

            $wallets = $this->lockWallets(array_filter([$original->source_wallet_id, $original->destination_wallet_id]));
            foreach ($wallets as $wallet) {
                $this->walletGuard->ensureCanOperate($wallet);
            }

            $refund = $this->createTransaction($actor, TransactionType::Refund, $original->destination_wallet_id, $original->source_wallet_id, $original->currency_code, $original->amount_minor, $reference, $description, $original->id);
            $this->lifecycle->transition($refund, TransactionStatus::Pending);
            $this->lifecycle->transition($refund, TransactionStatus::Processing);

            try {
                $entries = $original->ledgerTransaction()->with('entries.account')->firstOrFail()->entries;
                $lines = $entries->map(fn ($entry) => new PostingLine(
                    $entry->account_id,
                    $entry->direction === EntryDirection::Debit ? EntryDirection::Credit : EntryDirection::Debit,
                    Money::fromMinor($entry->amount_minor, $entry->account->currency_code),
                ))->all();
                $this->lockAndValidateDebits($lines);
                $journal = $this->ledger->post('journal:'.$refund->id, $lines, $description ?? 'Refund '.$original->reference);
                $refund->forceFill(['ledger_transaction_id' => $journal->id])->save();
                $this->lifecycle->transition($refund, TransactionStatus::Completed);
                $this->lifecycle->transition($original, TransactionStatus::Reversed, 'Refund '.$refund->id);
                $this->dispatchSuccessEvents($refund, $journal);
                TransactionCompleted::dispatch($refund->id);

                return [$refund->refresh(), null];
            } catch (InsufficientFundsException|FrozenWalletException $exception) {
                return [$this->fail($refund, $exception), $exception];
            }
        }, attempts: 3);

        return $this->unwrap($result);
    }

    private function process(TransactionType $type, User $actor, ?string $sourceId, ?string $destinationId, string $decimalAmount, string $reference, ?string $description, callable $linesFactory): FinancialTransaction
    {
        $result = DB::transaction(function () use ($type, $actor, $sourceId, $destinationId, $decimalAmount, $reference, $description, $linesFactory): array {
            $wallets = $this->lockWallets(array_filter([$sourceId, $destinationId]));
            $source = $sourceId ? $wallets->get($sourceId) : null;
            $destination = $destinationId ? $wallets->get($destinationId) : null;
            if (($source && $source->user_id !== $actor->getKey()) || ($type === TransactionType::Deposit && $destination->user_id !== $actor->getKey())) {
                throw new AuthorizationException;
            }
            if ($source && $destination && $source->currency_code !== $destination->currency_code) {
                throw new DomainException('Wallet currencies must match.');
            }

            $wallet = $source ?? $destination;
            $currency = Currency::query()->findOrFail($wallet->currency_code);
            $money = Money::fromDecimal($decimalAmount, $currency->code, $currency->minor_unit);
            $transaction = $this->createTransaction($actor, $type, $sourceId, $destinationId, $currency->code, $money->minor, $reference, $description);
            $this->lifecycle->transition($transaction, TransactionStatus::Pending);
            $this->lifecycle->transition($transaction, TransactionStatus::Processing);

            try {
                foreach ($wallets as $lockedWallet) {
                    $this->walletGuard->ensureCanOperate($lockedWallet);
                }
                $lines = $linesFactory($source, $destination, $money);
                $this->lockAndValidateDebits($lines);
                $journal = $this->ledger->post('journal:'.$transaction->id, $lines, $description ?? ucfirst($type->value).' '.$reference);
                $transaction->forceFill(['ledger_transaction_id' => $journal->id])->save();
                $this->lifecycle->transition($transaction, TransactionStatus::Completed);
                $this->dispatchSuccessEvents($transaction, $journal);
                TransactionCompleted::dispatch($transaction->id);

                return [$transaction->refresh(), null];
            } catch (InsufficientFundsException|FrozenWalletException $exception) {
                return [$this->fail($transaction, $exception), $exception];
            }
        }, attempts: 3);

        return $this->unwrap($result);
    }

    private function createTransaction(User $actor, TransactionType $type, ?string $sourceId, ?string $destinationId, string $currency, int $amount, string $reference, ?string $description, ?string $refundedId = null): FinancialTransaction
    {
        $transaction = FinancialTransaction::query()->create(['initiated_by' => $actor->id, 'type' => $type, 'status' => TransactionStatus::Created, 'source_wallet_id' => $sourceId, 'destination_wallet_id' => $destinationId, 'currency_code' => $currency, 'amount_minor' => $amount, 'reference' => $reference, 'description' => $description, 'refunded_transaction_id' => $refundedId]);
        $this->lifecycle->recordCreated($transaction);
        TransactionCreated::dispatch($transaction->id, $transaction->initiated_by, $transaction->type->value, $transaction->amount_minor, $transaction->currency_code);

        return $transaction;
    }

    private function lockWallets(array $ids): Collection
    {
        $ids = collect($ids)->unique()->sort()->values();
        $wallets = Wallet::query()->with('account')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($wallets->count() !== $ids->count()) {
            throw new DomainException('One or more wallets do not exist.');
        }

        return $wallets;
    }

    private function lockAndValidateDebits(array $lines): void
    {
        $ids = collect($lines)->pluck('accountId')->unique()->sort()->values();
        $accounts = Account::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($lines as $line) {
            $account = $accounts->get($line->accountId);
            if ($line->direction === EntryDirection::Debit && $account->wallet_id && $this->balances->forAccount($account)->balanceMinor < $line->money->minor) {
                throw new InsufficientFundsException;
            }
        }
    }

    private function fail(FinancialTransaction $transaction, \Throwable $exception): FinancialTransaction
    {
        $transaction->forceFill(['failure_code' => class_basename($exception), 'failure_message' => $exception->getMessage()])->save();
        $this->lifecycle->transition($transaction, TransactionStatus::Failed, $exception->getMessage());
        if ($transaction->type === TransactionType::Transfer) {
            TransferFailed::dispatch($transaction->id, $transaction->initiated_by, $transaction->source_wallet_id, $transaction->destination_wallet_id, $transaction->amount_minor, $transaction->currency_code, $exception->getMessage());
        }

        return $transaction->refresh();
    }

    private function dispatchSuccessEvents(FinancialTransaction $transaction, LedgerTransaction $journal): void
    {
        if ($transaction->type === TransactionType::Transfer) {
            TransferCompleted::dispatch($transaction->id, $transaction->initiated_by, $transaction->source_wallet_id, $transaction->destination_wallet_id, $transaction->amount_minor, $transaction->currency_code);
        }

        foreach ($journal->entries as $entry) {
            if (! $entry->account->wallet_id) {
                continue;
            }

            $event = $entry->direction === EntryDirection::Credit ? WalletCredited::class : WalletDebited::class;
            $event::dispatch($transaction->id, $entry->account->wallet_id, $entry->id, $entry->amount_minor, $transaction->currency_code);
        }
    }

    private function unwrap(array $result): FinancialTransaction
    {
        [$transaction, $error] = $result;
        if ($error) {
            throw new TransactionProcessingException($transaction->id, $error->getMessage());
        }

        return $transaction;
    }
}
