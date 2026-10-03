<?php

namespace App\Domains\Games\Services;

use App\Domains\Cards\Models\BingoCard;
use App\Domains\Financial\Models\Transaction;
use App\Domains\Financial\Services\LedgerService;
use App\Domains\Games\Events\PlayerJoinedGame;
use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameCard;
use App\Domains\Games\Models\GamePlayer;
use App\Domains\Tenancy\Models\GameManagerPlayer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class CardAssignmentService
{
    protected LedgerService $ledgerService;

    public function __construct(?LedgerService $ledgerService = null)
    {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

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

            // Check account status
            if (! $user->isActive()) {
                throw new InvalidArgumentException('Account is suspended. You cannot join games.');
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

            // Verify manager roster authorization and balance check if hosted by a Game Manager
            $creator = $lockedGame->creator ?? ($lockedGame->created_by ? User::find($lockedGame->created_by) : null);
            $isHostedByManager = $creator && $creator->isGameManager() && ! $creator->isCompanyAdmin();

            if ($isHostedByManager) {
                $managerPlayer = GameManagerPlayer::where('game_manager_id', $lockedGame->created_by)
                    ->where('player_id', $user->id)
                    ->first();

                if (! $managerPlayer) {
                    throw new RuntimeException('You have not been authorized by the Game Manager hosting this game. Please contact the manager to add you to their roster.');
                }

                if ($managerPlayer->balance <= 0) {
                    $hostName = $creator->name;
                    throw new RuntimeException("Your balance with {$hostName} is empty ($0.00). Please deposit with this manager to join.");
                }

                if ($managerPlayer->balance < $lockedGame->entry_fee) {
                    $bal = '$'.number_format($managerPlayer->balance / 100, 2);
                    $fee = '$'.number_format($lockedGame->entry_fee / 100, 2);
                    throw new RuntimeException("Insufficient balance with host Game Manager. Your balance is {$bal}, but entry fee is {$fee}. Please deposit funds to join.");
                }
            } else {
                if ($user->balance <= 0) {
                    throw new RuntimeException('Your account balance is empty ($0.00). Please deposit funds to join.');
                }

                if ($user->balance < $lockedGame->entry_fee) {
                    throw new RuntimeException("Insufficient wallet balance. Your balance is \${$user->formattedBalance()}, but entry fee is \${$lockedGame->formattedEntryFee()}.");
                }
            }

            // Handle entry fee deduction via ledger
            if ($lockedGame->entry_fee > 0) {
                $this->ledgerService->recordEntryFee($user, $lockedGame);
            }

            // Record game participation (cards are not assigned by default; manager assigns prior to start)
            $gamePlayer = GamePlayer::create([
                'game_id' => $lockedGame->id,
                'user_id' => $user->id,
                'entry_fee_paid' => $lockedGame->entry_fee,
                'joined_at' => now(),
            ]);

            $count = GamePlayer::where('game_id', $lockedGame->id)->count();
            try {
                event(new PlayerJoinedGame($lockedGame, $user, $count));
            } catch (\Throwable $e) {
                Log::warning("WebSocket broadcast skipped or failed for PlayerJoinedGame: {$e->getMessage()}");
            }

            return $gamePlayer;
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

        $gameCard = GameCard::create([
            'game_id' => $game->id,
            'user_id' => $user->id,
            'bingo_card_id' => $card->id,
            'bingo_card_version_id' => $card->current_version_id,
            'assigned_at' => now(),
        ]);

        $count = GamePlayer::where('game_id', $game->id)->count();
        try {
            event(new PlayerJoinedGame($game, $user, $count));
        } catch (\Throwable $e) {
            Log::warning("WebSocket broadcast skipped or failed for PlayerJoinedGame: {$e->getMessage()}");
        }

        return $gameCard;
    }

    /**
     * Assign (or reassign) a specific card number to a player in a game.
     */
    public function assignSpecificCardToPlayer(Game $game, User $user, int $cardNumber): GameCard
    {
        return DB::transaction(function () use ($game, $user, $cardNumber) {
            $lockedGame = Game::where('id', $game->id)->lockForUpdate()->firstOrFail();

            if (in_array($lockedGame->status, [Game::STATUS_COMPLETED, Game::STATUS_CANCELLED], true)) {
                throw new InvalidArgumentException("Cannot assign cards when game is in status: {$lockedGame->status}.");
            }

            // Verify company affiliation
            if ($user->company_id !== null && $user->company_id !== $lockedGame->company_id && ! $user->isPlatformOwner()) {
                throw new InvalidArgumentException('User does not belong to company for this game.');
            }

            // Ensure player record exists for this game
            $gamePlayer = GamePlayer::where('game_id', $lockedGame->id)
                ->where('user_id', $user->id)
                ->first();

            if (! $gamePlayer) {
                $currentCount = GamePlayer::where('game_id', $lockedGame->id)->count();
                if ($currentCount >= $lockedGame->max_players) {
                    throw new RuntimeException("Game room has reached maximum player capacity ({$lockedGame->max_players}).");
                }

                $gamePlayer = GamePlayer::create([
                    'game_id' => $lockedGame->id,
                    'user_id' => $user->id,
                    'entry_fee_paid' => $lockedGame->entry_fee,
                    'joined_at' => now(),
                ]);
            }

            // Find target card by card_number in company inventory with lock
            $targetCard = BingoCard::where('company_id', $lockedGame->company_id)
                ->where('card_number', $cardNumber)
                ->lockForUpdate()
                ->first();

            if (! $targetCard) {
                throw new InvalidArgumentException('Card #'.sprintf('%06d', $cardNumber).' does not exist in company inventory.');
            }

            if (in_array($targetCard->status, [BingoCard::STATUS_RETIRED, BingoCard::STATUS_DISABLED], true)) {
                throw new InvalidArgumentException('Card #'.sprintf('%06d', $cardNumber)." is {$targetCard->status} and cannot be assigned.");
            }

            if (! $targetCard->current_version_id) {
                throw new RuntimeException('Card #'.sprintf('%06d', $cardNumber).' has no active version.');
            }

            // Check if this card is already assigned to a DIFFERENT player in this game
            $alreadyAssignedInGame = GameCard::where('game_id', $lockedGame->id)
                ->where('bingo_card_id', $targetCard->id)
                ->where('user_id', '!=', $user->id)
                ->whereNull('released_at')
                ->exists();

            if ($alreadyAssignedInGame) {
                throw new InvalidArgumentException('Card #'.sprintf('%06d', $cardNumber).' is already assigned to another player in this game.');
            }

            // Check if card is in use in another active game
            $inOtherActiveGame = GameCard::where('bingo_card_id', $targetCard->id)
                ->where('game_id', '!=', $lockedGame->id)
                ->whereNull('released_at')
                ->whereHas('game', function ($q) {
                    $q->whereIn('status', [Game::STATUS_OPEN, Game::STATUS_STARTING, Game::STATUS_ACTIVE, Game::STATUS_PAUSED]);
                })
                ->exists();

            if ($inOtherActiveGame) {
                throw new InvalidArgumentException('Card #'.sprintf('%06d', $cardNumber).' is currently in use in another active game.');
            }

            // Find player's current card in this game if any
            $currentAssignment = GameCard::where('game_id', $lockedGame->id)
                ->where('user_id', $user->id)
                ->whereNull('released_at')
                ->first();

            if ($currentAssignment) {
                if ($currentAssignment->bingo_card_id === $targetCard->id) {
                    return $currentAssignment->load(['card', 'version', 'user']);
                }

                // Release old card back to available inventory if not used elsewhere
                $oldCard = BingoCard::find($currentAssignment->bingo_card_id);
                if ($oldCard && in_array($oldCard->status, [BingoCard::STATUS_ASSIGNED, BingoCard::STATUS_IN_USE], true)) {
                    $otherActiveUse = GameCard::where('bingo_card_id', $oldCard->id)
                        ->where('id', '!=', $currentAssignment->id)
                        ->whereNull('released_at')
                        ->exists();

                    if (! $otherActiveUse) {
                        $oldCard->update(['status' => BingoCard::STATUS_AVAILABLE]);
                    }
                }

                // Update current assignment with new card
                $currentAssignment->update([
                    'bingo_card_id' => $targetCard->id,
                    'bingo_card_version_id' => $targetCard->current_version_id,
                    'marked_positions' => [],
                    'assigned_at' => now(),
                ]);

                $gameCard = $currentAssignment;
            } else {
                $gameCard = GameCard::create([
                    'game_id' => $lockedGame->id,
                    'user_id' => $user->id,
                    'bingo_card_id' => $targetCard->id,
                    'bingo_card_version_id' => $targetCard->current_version_id,
                    'assigned_at' => now(),
                ]);
            }

            $targetCard->update(['status' => BingoCard::STATUS_ASSIGNED]);

            $count = GamePlayer::where('game_id', $lockedGame->id)->count();
            try {
                event(new PlayerJoinedGame($lockedGame, $user, $count));
            } catch (\Throwable $e) {
                Log::warning("WebSocket broadcast skipped or failed for PlayerJoinedGame: {$e->getMessage()}");
            }

            return $gameCard->load(['card', 'version', 'user']);
        });
    }

    /**
     * Assign a specific card number to a walk-in (offline / cash) player in a game.
     */
    public function assignWalkInCard(Game $game, int $cardNumber, ?string $guestIdentifier = null): GameCard
    {
        return DB::transaction(function () use ($game, $cardNumber, $guestIdentifier) {
            $lockedGame = Game::where('id', $game->id)->lockForUpdate()->firstOrFail();

            if (in_array($lockedGame->status, [Game::STATUS_COMPLETED, Game::STATUS_CANCELLED], true)) {
                throw new InvalidArgumentException("Cannot assign cards when game is in status: {$lockedGame->status}.");
            }

            // Check room capacity
            $currentCardsCount = GameCard::where('game_id', $lockedGame->id)->whereNull('released_at')->count();
            if ($currentCardsCount >= $lockedGame->max_players) {
                throw new RuntimeException("Game room has reached maximum player capacity ({$lockedGame->max_players}).");
            }

            // Find target card in company inventory
            $targetCard = BingoCard::where('company_id', $lockedGame->company_id)
                ->where('card_number', $cardNumber)
                ->lockForUpdate()
                ->first();

            if (! $targetCard) {
                throw new InvalidArgumentException('Card #'.sprintf('%06d', $cardNumber).' does not exist in company inventory.');
            }

            if (in_array($targetCard->status, [BingoCard::STATUS_RETIRED, BingoCard::STATUS_DISABLED], true)) {
                throw new InvalidArgumentException('Card #'.sprintf('%06d', $cardNumber)." is {$targetCard->status} and cannot be assigned.");
            }

            if (! $targetCard->current_version_id) {
                throw new RuntimeException('Card #'.sprintf('%06d', $cardNumber).' has no active version.');
            }

            // Check if card is already assigned in this game
            $alreadyAssignedInGame = GameCard::where('game_id', $lockedGame->id)
                ->where('bingo_card_id', $targetCard->id)
                ->whereNull('released_at')
                ->exists();

            if ($alreadyAssignedInGame) {
                throw new InvalidArgumentException('Card #'.sprintf('%06d', $cardNumber).' is already assigned to a player in this game.');
            }

            // Check if card is in use in another active game
            $inOtherActiveGame = GameCard::where('bingo_card_id', $targetCard->id)
                ->where('game_id', '!=', $lockedGame->id)
                ->whereNull('released_at')
                ->whereHas('game', function ($q) {
                    $q->whereIn('status', [Game::STATUS_OPEN, Game::STATUS_STARTING, Game::STATUS_ACTIVE, Game::STATUS_PAUSED]);
                })
                ->exists();

            if ($inOtherActiveGame) {
                throw new InvalidArgumentException('Card #'.sprintf('%06d', $cardNumber).' is currently in use in another active game.');
            }

            $formattedCardNumber = sprintf('#%06d', $cardNumber);
            $finalIdentifier = ! empty($guestIdentifier) ? $guestIdentifier : "Walk-in Cash Player ({$formattedCardNumber})";

            // Create walk-in GamePlayer entry
            GamePlayer::create([
                'game_id' => $lockedGame->id,
                'user_id' => null,
                'guest_identifier' => $finalIdentifier,
                'entry_fee_paid' => $lockedGame->entry_fee,
                'joined_at' => now(),
            ]);

            // Record cash walk-in entry fee transaction
            if ($lockedGame->entry_fee > 0) {
                Transaction::create([
                    'company_id' => $lockedGame->company_id,
                    'user_id' => null,
                    'game_manager_id' => $lockedGame->created_by,
                    'type' => Transaction::TYPE_ENTRY_FEE,
                    'amount' => $lockedGame->entry_fee,
                    'currency' => $lockedGame->currency ?? 'USD',
                    'status' => Transaction::STATUS_COMPLETED,
                    'balance_before' => 0,
                    'balance_after' => 0,
                    'reference_type' => Game::class,
                    'reference_id' => $lockedGame->id,
                    'reference_code' => "WALKIN-G{$lockedGame->id}-C{$cardNumber}",
                    'description' => "Cash walk-in entry fee for Game #{$lockedGame->game_number} ({$finalIdentifier})",
                ]);
            }

            // Create GameCard record
            $gameCard = GameCard::create([
                'game_id' => $lockedGame->id,
                'user_id' => null,
                'guest_identifier' => $finalIdentifier,
                'bingo_card_id' => $targetCard->id,
                'bingo_card_version_id' => $targetCard->current_version_id,
                'assigned_at' => now(),
            ]);

            $targetCard->update(['status' => BingoCard::STATUS_ASSIGNED]);

            return $gameCard->load(['card', 'version']);
        });
    }
}
