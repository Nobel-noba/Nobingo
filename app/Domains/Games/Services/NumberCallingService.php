<?php

namespace App\Domains\Games\Services;

use App\Domains\Games\Events\NumberCalled;
use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameCall;
use App\Domains\Games\Models\GameCard;
use App\Domains\Winners\Services\BingoVerificationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class NumberCallingService
{
    /**
     * Draw and call the next random ball for an active game using CSPRNG.
     */
    public function callNextNumber(Game $game): ?GameCall
    {
        return DB::transaction(function () use ($game) {
            // Lock game record
            $lockedGame = Game::where('id', $game->id)->lockForUpdate()->first();

            if (! $lockedGame || ! $lockedGame->isActive()) {
                throw new RuntimeException("Cannot call number for game in status: {$lockedGame?->status}. Game must be active.");
            }

            // Lock existing calls to strictly prevent duplicate sequence indexes and numbers
            $existingCalls = GameCall::where('game_id', $lockedGame->id)
                ->lockForUpdate()
                ->get(['sequence_index', 'ball_number']);

            $calledNumbers = $existingCalls->pluck('ball_number')->all();
            $callCount = count($calledNumbers);

            if ($callCount >= 75) {
                return null;
            }

            // Determine uncalled numbers
            $remaining = array_values(array_diff(range(1, 75), $calledNumbers));
            if (empty($remaining)) {
                return null;
            }

            // Cryptographically secure draw without replacement
            $pickedIndex = random_int(0, count($remaining) - 1);
            $pickedNumber = $remaining[$pickedIndex];
            $letter = GameCall::getLetterForNumber($pickedNumber);
            $sequenceIndex = $callCount + 1;

            $call = GameCall::create([
                'game_id' => $lockedGame->id,
                'sequence_index' => $sequenceIndex,
                'ball_number' => $pickedNumber,
                'letter' => $letter,
                'called_at' => now(),
            ]);

            // Auto-mark cards participating in this game
            $markedCardsCount = $this->autoDaubCardsForNumber($lockedGame, $pickedNumber);

            $remainingCount = count($remaining) - 1;

            // Dispatch event for real-time listeners and auditing
            try {
                event(new NumberCalled($lockedGame, $call, $remainingCount, $markedCardsCount));
            } catch (\Throwable $e) {
                Log::warning("WebSocket broadcast skipped or failed for NumberCalled: {$e->getMessage()}");
            }

            // Check for automatic bingo detection if configured
            $autoClaim = ($lockedGame->configuration_snapshot['auto_claim'] ?? false)
                || ($lockedGame->configuration_snapshot['automatic_bingo_detection'] ?? false);

            if ($autoClaim) {
                app(BingoVerificationService::class)->checkAutomaticWinners($lockedGame, $call);
            }

            return $call;
        });
    }

    /**
     * Automatically mark coordinates on all cards holding the called number.
     */
    public function autoDaubCardsForNumber(Game $game, int $number): int
    {
        $cards = GameCard::with('version')
            ->where('game_id', $game->id)
            ->get();

        $markedCount = 0;

        foreach ($cards as $card) {
            $grid = $card->version->grid;

            for ($row = 0; $row < 5; $row++) {
                for ($col = 0; $col < 5; $col++) {
                    if ($grid[$row][$col] === $number) {
                        if ($card->markPosition($row, $col)) {
                            $markedCount++;
                        }
                    }
                }
            }
        }

        return $markedCount;
    }

    /**
     * Daub a specific position on a game card with server-side validation.
     */
    public function manualDaub(GameCard $gameCard, int $row, int $col): bool
    {
        if ($row < 0 || $row > 4 || $col < 0 || $col > 4) {
            throw new InvalidArgumentException("Coordinates out of bounds: [{$row}, {$col}]. Must be between 0 and 4.");
        }

        // Center FREE square is always daubable
        if ($row === 2 && $col === 2) {
            $gameCard->markPosition(2, 2);

            return true;
        }

        $grid = $gameCard->version->grid;
        $cellNumber = $grid[$row][$col];

        // Authoritatively verify the number was actually called in this game
        $wasCalled = GameCall::where('game_id', $gameCard->game_id)
            ->where('ball_number', $cellNumber)
            ->exists();

        if (! $wasCalled) {
            throw new InvalidArgumentException("Number {$cellNumber} has not been called in this game yet.");
        }

        $gameCard->markPosition($row, $col);

        return true;
    }

    /**
     * Get remaining uncalled numbers for a game.
     *
     * @return list<int>
     */
    public function getRemainingNumbers(Game $game): array
    {
        $called = GameCall::where('game_id', $game->id)->pluck('ball_number')->all();

        return array_values(array_diff(range(1, 75), $called));
    }

    /**
     * Get chronological call history.
     *
     * @return Collection<int, GameCall>
     */
    public function getCallHistory(Game $game): Collection
    {
        return GameCall::where('game_id', $game->id)
            ->orderBy('sequence_index')
            ->get();
    }

    /**
     * Check if a specific number has been called in a game.
     */
    public function isNumberCalled(Game $game, int $number): bool
    {
        return GameCall::where('game_id', $game->id)
            ->where('ball_number', $number)
            ->exists();
    }

    /**
     * Generate master caller board grouped by B-I-N-G-O columns with call states.
     *
     * @return array<string, list<array{number: int, is_called: bool, sequence_index: ?int, called_at: ?string}>>
     */
    public function getMasterBoard(Game $game): array
    {
        $calls = GameCall::where('game_id', $game->id)->get()->keyBy('ball_number');

        $columns = [
            'B' => range(1, 15),
            'I' => range(16, 30),
            'N' => range(31, 45),
            'G' => range(46, 60),
            'O' => range(61, 75),
        ];

        $board = [];

        foreach ($columns as $letter => $numbers) {
            $board[$letter] = [];

            foreach ($numbers as $num) {
                /** @var GameCall|null $call */
                $call = $calls->get($num);

                $board[$letter][] = [
                    'number' => $num,
                    'is_called' => $call !== null,
                    'sequence_index' => $call?->sequence_index,
                    'called_at' => $call?->called_at?->toIso8601String(),
                ];
            }
        }

        return $board;
    }
}
