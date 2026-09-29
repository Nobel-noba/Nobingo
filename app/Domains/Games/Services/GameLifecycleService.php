<?php

namespace App\Domains\Games\Services;

use App\Domains\Cards\Models\BingoCard;
use App\Domains\Financial\Models\Transaction;
use App\Domains\Financial\Services\LedgerService;
use App\Domains\Games\Events\GameStateChanged;
use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameCard;
use App\Domains\Games\Models\GameTemplate;
use App\Domains\Platform\Models\PlatformSetting;
use App\Domains\Tenancy\Models\Company;
use App\Domains\Winners\Models\GameWinner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class GameLifecycleService
{
    protected LedgerService $ledgerService;

    public function __construct(?LedgerService $ledgerService = null)
    {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * Create a new game with an immutable configuration snapshot based on a template.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function createFromTemplate(
        GameTemplate $template,
        Company|int $company,
        array $overrides = [],
        ?int $userId = null
    ): Game {
        $companyId = $company instanceof Company ? $company->id : $company;

        return DB::transaction(function () use ($template, $companyId, $overrides, $userId) {
            $maxNumber = Game::where('company_id', $companyId)->lockForUpdate()->max('game_number') ?? 1000;
            $nextNumber = $maxNumber + 1;

            $patternIds = $overrides['allowed_pattern_ids'] ?? $template->allowed_pattern_ids;
            $requiredCount = (int) ($overrides['required_pattern_count'] ?? $template->required_pattern_count);
            $winnerPolicy = $overrides['winner_policy'] ?? $template->winner_policy;
            $callInterval = (int) ($overrides['call_interval'] ?? $template->default_call_interval);

            // Construct immutable configuration snapshot
            $snapshot = array_merge([
                'template_id' => $template->id,
                'template_name' => $template->name,
                'pattern_mode' => $overrides['pattern_mode'] ?? $template->pattern_mode,
                'required_pattern_count' => $requiredCount,
                'allowed_pattern_ids' => $patternIds,
                'winner_policy' => $winnerPolicy,
                'call_interval' => $callInterval,
                'auto_claim' => $overrides['auto_claim'] ?? false,
                'snapshot_created_at' => now()->toIso8601String(),
            ], $overrides['configuration_snapshot'] ?? []);

            return Game::create([
                'company_id' => $companyId,
                'game_template_id' => $template->id,
                'game_number' => $nextNumber,
                'name' => $overrides['name'] ?? $template->name." #{$nextNumber}",
                'description' => $overrides['description'] ?? $template->description,
                'status' => $overrides['status'] ?? Game::STATUS_DRAFT,
                'configuration_snapshot' => $snapshot,
                'entry_fee' => (int) ($overrides['entry_fee'] ?? $template->default_entry_fee),
                'currency' => $overrides['currency'] ?? 'USD',
                'min_players' => (int) ($overrides['min_players'] ?? $template->default_min_players),
                'max_players' => (int) ($overrides['max_players'] ?? $template->default_max_players),
                'scheduled_start_at' => $overrides['scheduled_start_at'] ?? null,
                'call_interval' => $callInterval,
                'winner_policy' => $winnerPolicy,
                'prize_configuration' => $overrides['prize_configuration'] ?? $template->default_prize_configuration,
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * Transition a game to a new status with validation and lifecycle hooks.
     */
    public function transitionTo(Game $game, string $targetStatus): void
    {
        // Idempotency: if already in the target status, do nothing
        if ($game->status === $targetStatus) {
            return;
        }

        if (! $game->canTransitionTo($targetStatus)) {
            throw new InvalidArgumentException(
                "Illegal game state transition from [{$game->status}] to [{$targetStatus}]."
            );
        }

        $previousStatus = $game->status;

        DB::transaction(function () use ($game, $targetStatus) {
            $updates = ['status' => $targetStatus];

            if (in_array($targetStatus, [Game::STATUS_STARTING, Game::STATUS_ACTIVE], true) && $game->started_at === null) {
                $updates['started_at'] = now();
            }

            if (in_array($targetStatus, [Game::STATUS_COMPLETED, Game::STATUS_CANCELLED], true)) {
                $updates['ended_at'] = now();

                // Release assigned cards back to available inventory
                $this->releaseGameCards($game);
            }

            if ($targetStatus === Game::STATUS_COMPLETED) {
                $this->finalizeGameFinancials($game, $updates);
            }

            $game->update($updates);
        });

        try {
            event(new GameStateChanged($game->fresh(), $previousStatus));
        } catch (\Throwable $e) {
            Log::warning("WebSocket broadcast skipped or failed for GameStateChanged: {$e->getMessage()}");
        }
    }

    /**
     * Finalize pot calculation, winner payouts, and house/platform revenue splits.
     *
     * @param  array<string, mixed>  $updates
     */
    protected function finalizeGameFinancials(Game $game, array &$updates): void
    {
        $playerCount = $game->players()->count();
        $totalPot = (int) ($game->players()->sum('entry_fee_paid') ?: ($playerCount * $game->entry_fee));

        $revenueSettings = PlatformSetting::getRevenueSettings();
        $winnerSharePct = (float) ($game->winner_share_percentage ?? $revenueSettings['winner_share_percentage'] ?? 75.0);
        $platformSharePct = (float) ($game->platform_share_percentage ?? $revenueSettings['platform_fee_percentage'] ?? 20.0);

        $actualWinnerPayout = (int) GameWinner::where('game_id', $game->id)->sum('payout_amount');
        $winnerPayoutTotal = $actualWinnerPayout > 0 ? $actualWinnerPayout : (int) floor($totalPot * ($winnerSharePct / 100));

        $houseGrossCut = max(0, $totalPot - $winnerPayoutTotal);
        $platformFee = (int) floor($houseGrossCut * ($platformSharePct / 100));
        $companyNetCut = max(0, $houseGrossCut - $platformFee);

        $updates['total_pot'] = $totalPot;
        $updates['winner_payout_total'] = $winnerPayoutTotal;
        $updates['house_gross_cut'] = $houseGrossCut;
        $updates['platform_fee'] = $platformFee;
        $updates['company_net_cut'] = $companyNetCut;
        $updates['winner_share_percentage'] = $winnerSharePct;
        $updates['platform_share_percentage'] = $platformSharePct;

        if ($platformFee > 0) {
            $company = Company::where('id', $game->company_id)->lockForUpdate()->first();
            if ($company) {
                $balanceBefore = (int) $company->credit_balance;
                $balanceAfter = max(0, $balanceBefore - $platformFee);
                $company->update(['credit_balance' => $balanceAfter]);

                Transaction::create([
                    'company_id' => $company->id,
                    'user_id' => null,
                    'type' => Transaction::TYPE_PLATFORM_FEE,
                    'amount' => $platformFee,
                    'currency' => $game->currency ?? 'USD',
                    'status' => Transaction::STATUS_COMPLETED,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'reference_type' => Game::class,
                    'reference_id' => $game->id,
                    'reference_code' => "PFEE-G{$game->id}",
                    'description' => "Platform fee ({$platformSharePct}% of house cut) for Game #{$game->id} ({$game->name})",
                ]);
            }
        }
    }

    /**
     * Release cards assigned to this game back to the available inventory.
     */
    protected function releaseGameCards(Game $game): void
    {
        $gameCards = GameCard::where('game_id', $game->id)->whereNull('released_at')->get();

        foreach ($gameCards as $gc) {
            $gc->update(['released_at' => now()]);

            // If card is not retired or disabled, mark as available again
            $card = BingoCard::find($gc->bingo_card_id);
            if ($card && in_array($card->status, [BingoCard::STATUS_IN_USE, BingoCard::STATUS_ASSIGNED], true)) {
                $card->update(['status' => BingoCard::STATUS_AVAILABLE]);
            }
        }
    }

    public function openGame(Game $game): void
    {
        $this->transitionTo($game, Game::STATUS_OPEN);
    }

    public function startGame(Game $game): void
    {
        if ($game->status === Game::STATUS_OPEN) {
            $this->transitionTo($game, Game::STATUS_STARTING);
        }
        $this->transitionTo($game, Game::STATUS_ACTIVE);
    }

    public function pauseGame(Game $game): void
    {
        $this->transitionTo($game, Game::STATUS_PAUSED);
    }

    public function resumeGame(Game $game): void
    {
        $this->transitionTo($game, Game::STATUS_ACTIVE);
    }

    public function completeGame(Game $game): void
    {
        $this->transitionTo($game, Game::STATUS_COMPLETED);
    }

    public function cancelGame(Game $game): void
    {
        $this->transitionTo($game, Game::STATUS_CANCELLED);

        // Refund any players who paid an entry fee
        if ($game->entry_fee > 0) {
            $players = $game->players()->with('user')->get();
            foreach ($players as $player) {
                if ($player->user) {
                    $this->ledgerService->recordRefund(
                        $player->user,
                        $game,
                        $game->entry_fee,
                        "Refund for cancelled Game #{$game->id} ({$game->name})"
                    );
                }
            }
        }
    }
}
