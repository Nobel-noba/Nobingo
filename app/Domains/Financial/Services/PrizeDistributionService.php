<?php

namespace App\Domains\Financial\Services;

use App\Domains\Financial\Events\PrizeDistributed;
use App\Domains\Financial\Models\Transaction;
use App\Domains\Games\Models\Game;
use App\Domains\Winners\Models\GameWinner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrizeDistributionService
{
    public function __construct(
        protected LedgerService $ledgerService,
        protected PrizeCalculationService $calculationService
    ) {}

    /**
     * Distribute prize payouts to all pending verified winners of a completed game.
     *
     * @return Collection<int, GameWinner>
     */
    public function distributePrizesForGame(Game $game): Collection
    {
        return DB::transaction(function () use ($game) {
            /** @var Collection<int, GameWinner> $winners */
            $winners = GameWinner::where('game_id', $game->id)
                ->where('payout_status', GameWinner::PAYOUT_STATUS_PENDING)
                ->lockForUpdate()
                ->get();

            foreach ($winners as $winner) {
                $this->distributeSingleWinner($winner, $game);
            }

            return $winners->fresh();
        });
    }

    /**
     * Distribute prize to a single winner.
     */
    public function distributeSingleWinner(GameWinner $winner, ?Game $game = null): ?Transaction
    {
        $game = $game ?? $winner->game;

        if ($winner->isWalkIn() || $winner->user_id === null) {
            $winner->update([
                'payout_status' => GameWinner::PAYOUT_STATUS_PAID,
                'paid_at' => now(),
            ]);

            $transaction = null;
            if ($winner->payout_amount > 0) {
                $identifier = $winner->card?->guest_identifier ?? "Walk-in Winner (#{$winner->id})";
                $transaction = Transaction::create([
                    'company_id' => $winner->company_id,
                    'user_id' => null,
                    'game_manager_id' => $game->created_by,
                    'type' => Transaction::TYPE_PRIZE,
                    'amount' => $winner->payout_amount,
                    'currency' => 'USD',
                    'status' => Transaction::STATUS_COMPLETED,
                    'balance_before' => 0,
                    'balance_after' => 0,
                    'reference_type' => GameWinner::class,
                    'reference_id' => $winner->id,
                    'reference_code' => "PRIZE-WALKIN-GW{$winner->id}",
                    'description' => "Cash walk-in prize payout for Game #{$winner->game_id} ({$identifier})",
                ]);
            }

            try {
                event(new PrizeDistributed($game, $winner, $winner->payout_amount));
            } catch (\Throwable $e) {
                Log::warning("WebSocket broadcast skipped or failed for PrizeDistributed: {$e->getMessage()}");
            }

            return $transaction;
        }

        $transaction = $this->ledgerService->recordPrizePayout($winner);

        try {
            event(new PrizeDistributed($game, $winner, $winner->payout_amount));
        } catch (\Throwable $e) {
            Log::warning("WebSocket broadcast skipped or failed for PrizeDistributed: {$e->getMessage()}");
        }

        return $transaction;
    }
}
