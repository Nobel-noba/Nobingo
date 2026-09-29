<?php

namespace App\Domains\Financial\Services;

use App\Domains\Financial\Events\TransactionCreated;
use App\Domains\Financial\Models\Transaction;
use App\Domains\Games\Models\Game;
use App\Domains\Winners\Models\GameWinner;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class LedgerService
{
    /**
     * Record an entry fee debit transaction for a player entering a game.
     *
     * @throws DomainException
     */
    public function recordEntryFee(User $user, Game $game): Transaction
    {
        if ($game->entry_fee <= 0) {
            throw new InvalidArgumentException('Entry fee must be greater than zero to record a financial transaction.');
        }

        return DB::transaction(function () use ($user, $game) {
            // Idempotency: prevent double charging for the same game
            $existing = Transaction::where('company_id', $game->company_id)
                ->where('user_id', $user->id)
                ->where('type', Transaction::TYPE_ENTRY_FEE)
                ->where('reference_type', Game::class)
                ->where('reference_id', $game->id)
                ->first();

            if ($existing) {
                return $existing;
            }

            /** @var User $lockedUser */
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            if ($lockedUser->balance < $game->entry_fee) {
                throw new \RuntimeException(
                    "Insufficient wallet balance. Required: {$game->formattedEntryFee()}, available: {$lockedUser->formattedBalance()}."
                );
            }

            $balanceBefore = $lockedUser->balance;
            $balanceAfter = $balanceBefore - $game->entry_fee;

            $lockedUser->update(['balance' => $balanceAfter]);

            $transaction = Transaction::create([
                'company_id' => $game->company_id,
                'user_id' => $user->id,
                'type' => Transaction::TYPE_ENTRY_FEE,
                'amount' => $game->entry_fee,
                'currency' => $game->currency ?? 'USD',
                'status' => Transaction::STATUS_COMPLETED,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => Game::class,
                'reference_id' => $game->id,
                'reference_code' => "ENTRY-G{$game->id}-U{$user->id}",
                'description' => "Entry fee for Game #{$game->id} ({$game->name})",
            ]);

            $this->dispatchTransactionCreated($transaction);

            return $transaction;
        });
    }

    /**
     * Record a prize payout credit transaction for a verified game winner.
     */
    public function recordPrizePayout(GameWinner $winner): Transaction
    {
        if ($winner->payout_amount <= 0) {
            $winner->update(['payout_status' => GameWinner::PAYOUT_STATUS_PAID]);

            return Transaction::make([
                'company_id' => $winner->company_id,
                'user_id' => $winner->user_id,
                'type' => Transaction::TYPE_PRIZE,
                'amount' => 0,
                'status' => Transaction::STATUS_COMPLETED,
            ]);
        }

        return DB::transaction(function () use ($winner) {
            // Idempotency: prevent double payout
            $existing = Transaction::where('company_id', $winner->company_id)
                ->where('user_id', $winner->user_id)
                ->where('type', Transaction::TYPE_PRIZE)
                ->where('reference_type', GameWinner::class)
                ->where('reference_id', $winner->id)
                ->first();

            if ($existing) {
                $winner->update(['payout_status' => GameWinner::PAYOUT_STATUS_PAID]);

                return $existing;
            }

            /** @var User $lockedUser */
            $lockedUser = User::where('id', $winner->user_id)->lockForUpdate()->firstOrFail();

            $balanceBefore = $lockedUser->balance;
            $balanceAfter = $balanceBefore + $winner->payout_amount;

            $lockedUser->update(['balance' => $balanceAfter]);

            $transaction = Transaction::create([
                'company_id' => $winner->company_id,
                'user_id' => $winner->user_id,
                'type' => Transaction::TYPE_PRIZE,
                'amount' => $winner->payout_amount,
                'currency' => 'USD',
                'status' => Transaction::STATUS_COMPLETED,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => GameWinner::class,
                'reference_id' => $winner->id,
                'reference_code' => "PRIZE-GW{$winner->id}",
                'description' => "Prize payout for Game #{$winner->game_id}",
            ]);

            $winner->update(['payout_status' => GameWinner::PAYOUT_STATUS_PAID]);

            $this->dispatchTransactionCreated($transaction);

            return $transaction;
        });
    }

    /**
     * Record a refund credit transaction when a game is cancelled or player refunded.
     */
    public function recordRefund(User $user, Game $game, int $amount, ?string $reason = null): Transaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Refund amount must be greater than zero.');
        }

        return DB::transaction(function () use ($user, $game, $amount, $reason) {
            // Idempotency: prevent duplicate refund for the same game
            $existing = Transaction::where('company_id', $game->company_id)
                ->where('user_id', $user->id)
                ->where('type', Transaction::TYPE_REFUND)
                ->where('reference_type', Game::class)
                ->where('reference_id', $game->id)
                ->first();

            if ($existing) {
                return $existing;
            }

            /** @var User $lockedUser */
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            $balanceBefore = $lockedUser->balance;
            $balanceAfter = $balanceBefore + $amount;

            $lockedUser->update(['balance' => $balanceAfter]);

            $transaction = Transaction::create([
                'company_id' => $game->company_id,
                'user_id' => $user->id,
                'type' => Transaction::TYPE_REFUND,
                'amount' => $amount,
                'currency' => $game->currency ?? 'USD',
                'status' => Transaction::STATUS_COMPLETED,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => Game::class,
                'reference_id' => $game->id,
                'reference_code' => "REFUND-G{$game->id}-U{$user->id}",
                'description' => $reason ?? "Refund for cancelled Game #{$game->id} ({$game->name})",
            ]);

            $this->dispatchTransactionCreated($transaction);

            return $transaction;
        });
    }

    /**
     * Record a wallet deposit transaction.
     */
    public function recordDeposit(User $user, int $amount, ?string $referenceCode = null, ?string $description = null): Transaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Deposit amount must be greater than zero.');
        }

        return DB::transaction(function () use ($user, $amount, $referenceCode, $description) {
            /** @var User $lockedUser */
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            $balanceBefore = $lockedUser->balance;
            $balanceAfter = $balanceBefore + $amount;

            $lockedUser->update(['balance' => $balanceAfter]);

            $transaction = Transaction::create([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'type' => Transaction::TYPE_DEPOSIT,
                'amount' => $amount,
                'currency' => 'USD',
                'status' => Transaction::STATUS_COMPLETED,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_code' => $referenceCode ?? ('DEP-'.bin2hex(random_bytes(6))),
                'description' => $description ?? 'Wallet deposit',
            ]);

            $this->dispatchTransactionCreated($transaction);

            return $transaction;
        });
    }

    /**
     * Record a wallet withdrawal transaction.
     *
     * @throws DomainException
     */
    public function recordWithdrawal(User $user, int $amount, ?string $referenceCode = null, ?string $description = null): Transaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Withdrawal amount must be greater than zero.');
        }

        return DB::transaction(function () use ($user, $amount, $referenceCode, $description) {
            /** @var User $lockedUser */
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            if ($lockedUser->balance < $amount) {
                throw new \RuntimeException(
                    'Insufficient wallet balance. Requested: $'.number_format($amount / 100, 2).", available: {$lockedUser->formattedBalance()}."
                );
            }

            $balanceBefore = $lockedUser->balance;
            $balanceAfter = $balanceBefore - $amount;

            $lockedUser->update(['balance' => $balanceAfter]);

            $transaction = Transaction::create([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'type' => Transaction::TYPE_WITHDRAWAL,
                'amount' => $amount,
                'currency' => 'USD',
                'status' => Transaction::STATUS_COMPLETED,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_code' => $referenceCode ?? ('WTH-'.bin2hex(random_bytes(6))),
                'description' => $description ?? 'Wallet withdrawal',
            ]);

            $this->dispatchTransactionCreated($transaction);

            return $transaction;
        });
    }

    /**
     * Record an administrative balance adjustment.
     */
    public function recordAdjustment(User $user, int $amount, bool $isCredit, string $reason): Transaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Adjustment amount must be greater than zero.');
        }

        return DB::transaction(function () use ($user, $amount, $isCredit, $reason) {
            /** @var User $lockedUser */
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            $balanceBefore = $lockedUser->balance;

            if (! $isCredit && $balanceBefore < $amount) {
                throw new DomainException("Cannot adjust balance below zero. Balance: {$lockedUser->formattedBalance()}, requested deduction: \$".number_format($amount / 100, 2));
            }

            $balanceAfter = $isCredit ? $balanceBefore + $amount : $balanceBefore - $amount;

            $lockedUser->update(['balance' => $balanceAfter]);

            $transaction = Transaction::create([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'type' => Transaction::TYPE_ADJUSTMENT,
                'amount' => $amount,
                'currency' => 'USD',
                'status' => Transaction::STATUS_COMPLETED,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_code' => 'ADJ-'.bin2hex(random_bytes(6)),
                'description' => "Admin Adjustment: {$reason}",
            ]);

            $this->dispatchTransactionCreated($transaction);

            return $transaction;
        });
    }

    /**
     * Safely dispatch transaction event without failing if WebSocket broadcaster is unavailable.
     */
    private function dispatchTransactionCreated(Transaction $transaction): void
    {
        try {
            event(new TransactionCreated($transaction));
        } catch (\Throwable $e) {
            Log::warning('Failed to broadcast TransactionCreated event: '.$e->getMessage(), [
                'transaction_id' => $transaction->id,
            ]);
        }
    }
}
