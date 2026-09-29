<?php

namespace App\Domains\Financial\Services;

use App\Domains\Games\Models\Game;

class PrizeCalculationService
{
    /**
     * Calculate the total eligible prize pool for a given game.
     */
    public function calculateTotalPrizePool(Game $game): int
    {
        $config = $game->prize_configuration ?? [];
        $playerCount = $game->players()->count();
        $totalEntryPool = $game->entry_fee * $playerCount;

        // 1. Fixed prize takes absolute priority if specified
        if (isset($config['fixed_prize'])) {
            return (int) $config['fixed_prize'];
        }

        if (isset($config['total'])) {
            return (int) $config['total'];
        }

        // 2. Percentage of entry fee pot
        if (isset($config['pot_percentage'])) {
            $percentage = (float) $config['pot_percentage'];

            return (int) floor($totalEntryPool * ($percentage / 100));
        }

        // 3. Fallback: 80% of entry fee pool, or minimum guaranteed pot of $100.00 (10,000 cents)
        if ($totalEntryPool > 0) {
            return (int) floor($totalEntryPool * 0.80);
        }

        return 10000;
    }

    /**
     * Calculate the prize amount for a winner at a specific position (1st, 2nd, etc.),
     * divided equally among any tied co-winners at that exact position.
     */
    public function calculatePrizeForPosition(Game $game, int $position = 1, int $winnersInPosition = 1): int
    {
        $winnersInPosition = max(1, $winnersInPosition);
        $totalPool = $this->calculateTotalPrizePool($game);
        $config = $game->prize_configuration ?? [];

        $positionSharePercentage = $this->getPositionPercentage($config, $position);
        $positionPool = (int) floor($totalPool * ($positionSharePercentage / 100));

        return (int) floor($positionPool / $winnersInPosition);
    }

    /**
     * Resolve the percentage of the total prize pool assigned to a specific ranking position.
     *
     * @param  array<string, mixed>  $config
     */
    protected function getPositionPercentage(array $config, int $position): float
    {
        // Check for 'positions' or 'tier_percentages' array: e.g. [1 => 70, 2 => 20, 3 => 10]
        $positions = $config['positions'] ?? $config['tier_percentages'] ?? null;

        if (is_array($positions)) {
            if (isset($positions[$position])) {
                return (float) $positions[$position];
            }
            if (isset($positions[(string) $position])) {
                return (float) $positions[(string) $position];
            }
        }

        // Default: 1st position receives 100% of the prize pool
        return $position === 1 ? 100.0 : 0.0;
    }
}
