<?php

namespace App\Domains\Games\Services;

use App\Domains\Cards\Models\BingoCard;
use App\Domains\Games\Events\PlayerJoinedGame;
use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameCard;
use App\Domains\Games\Models\GamePlayer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CardAssignmentService
{
    /**
     * Join an OPEN game and receive an assigned fixed card from company inventory.
     */
    public function joinGame(Game $game, User $user): GamePlayer
    {
        return DB::transaction(function () use ($game, $user) {
            // Lock game record
            $lockedGame = Game::where('id', $game->id)->lockForUpdate()->first();

            if (! $lockedGame || ! $lockedGame->isOpen()) {
                throw new InvalidArgumentException("Game is not open for joining. Current status: {$lockedGame?->status}.");
            }

            // Verify company affiliation
            if ($user->company_id !== null && $user->company_id !== $lockedGame->company_id && ! $user->isPlatformOwner()) {
                throw new InvalidArgumentException('User does not belong to company for this game.');
            }

            // Check if player has already joined
            $alreadyJoined = GamePlayer::where('game_id', $lockedGame->id)
                ->where('user_id', $user->id)
                ->exists();

            if ($alreadyJoined) {
                throw new InvalidArgumentException('Player has already joined this game.');
            }

            // Check max player cap
            $currentCount = GamePlayer::where('game_id', $lockedGame->id)->count();
            if ($currentCount >= $lockedGame->max_players) {
                throw new RuntimeException("Game room has reached maximum player capacity ({$lockedGame->max_players}).");
            }

            // Handle entry fee deduction
            if ($lockedGame->entry_fee > 0) {
                $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
                if ($lockedUser->balance < $lockedGame->entry_fee) {
                    throw new RuntimeException(
                        "Insufficient wallet balance. Required: {$lockedGame->formattedEntryFee()}, available: {$lockedUser->formattedBalance()}."
                    );
                }

                $lockedUser->decrement('balance', $lockedGame->entry_fee);
            }

            // Record game participation
            $gamePlayer = GamePlayer::create([
                'game_id' => $lockedGame->id,
                'user_id' => $user->id,
                'entry_fee_paid' => $lockedGame->entry_fee,
                'joined_at' => now(),
            ]);

            // Assign fixed card from company inventory
            $this->assignCardToPlayer($lockedGame, $user);

            $count = GamePlayer::where('game_id', $lockedGame->id)->count();
            event(new PlayerJoinedGame($lockedGame, $user, $count));

            return $gamePlayer->load('assignedCard.version');
        });
    }

    /**
     * Assign a fixed card from the company inventory to a player for a specific game.
     */
    public function assignCardToPlayer(Game $game, User $user): GameCard
    {
        // Enforce: Player must not already hold a card in this game
        $existing = GameCard::where('game_id', $game->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($existing) {
            throw new InvalidArgumentException('Player already has an assigned card in this game.');
        }

        // Find available card from company inventory with row locking
        $card = BingoCard::where('company_id', $game->company_id)
            ->where('status', BingoCard::STATUS_AVAILABLE)
            ->whereDoesntHave('gameCards', function ($q) use ($game) {
                $q->where('game_id', $game->id);
            })
            ->lockForUpdate()
            ->first();

        if (! $card) {
            throw new RuntimeException('No available cards in company inventory for game assignment.');
        }

        if (! $card->current_version_id) {
            throw new RuntimeException("Card #{$card->formattedCardNumber()} has no active version.");
        }

        // Mark card as assigned
        $card->update(['status' => BingoCard::STATUS_ASSIGNED]);

        return GameCard::create([
            'game_id' => $game->id,
            'user_id' => $user->id,
            'bingo_card_id' => $card->id,
            'bingo_card_version_id' => $card->current_version_id,
            'assigned_at' => now(),
        ]);
    }
}
