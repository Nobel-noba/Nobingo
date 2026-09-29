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

            try {
                event(new PrizeDistributed($game, $winner, $winner->payout_amount));
            } catch (\Throwable $e) {
                Log::warning("WebSocket broadcast skipped or failed for PrizeDistributed: {$e->getMessage()}");
            }

            return null;
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
