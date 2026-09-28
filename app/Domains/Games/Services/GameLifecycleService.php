<?php

namespace App\Domains\Games\Services;

use App\Domains\Cards\Models\BingoCard;
use App\Domains\Games\Events\GameStateChanged;
use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameCard;
use App\Domains\Games\Models\GameTemplate;
use App\Domains\Tenancy\Models\Company;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GameLifecycleService
{
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
            $snapshot = [
                'template_id' => $template->id,
                'template_name' => $template->name,
                'pattern_mode' => $overrides['pattern_mode'] ?? $template->pattern_mode,
                'required_pattern_count' => $requiredCount,
                'allowed_pattern_ids' => $patternIds,
                'winner_policy' => $winnerPolicy,
                'call_interval' => $callInterval,
                'snapshot_created_at' => now()->toIso8601String(),
            ];

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

            $game->update($updates);
        });

        event(new GameStateChanged($game->fresh(), $previousStatus));
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
    }
}
