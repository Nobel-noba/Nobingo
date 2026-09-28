<?php

namespace App\Domains\Patterns\Services;

use App\Domains\Patterns\Models\WinningPattern;
use Illuminate\Support\Collection;

class WinningPatternService
{
    /**
     * Build a 5x5 boolean marked matrix given a fixed card grid and called numbers.
     * The center square [2, 2] is automatically considered marked (FREE).
     *
     * @param  array<int, array<int, int>>  $cardGrid
     * @param  list<int>  $calledNumbers
     * @return array<int, array<int, bool>>
     */
    public function getMarkedGrid(array $cardGrid, array $calledNumbers): array
    {
        $calledLookup = array_flip($calledNumbers);
        $marked = [];

        for ($row = 0; $row < 5; $row++) {
            $marked[$row] = [];
            for ($col = 0; $col < 5; $col++) {
                // Center cell is always marked
                if ($row === 2 && $col === 2) {
                    $marked[$row][$col] = true;

                    continue;
                }

                $cellNumber = $cardGrid[$row][$col] ?? null;
                $marked[$row][$col] = $cellNumber !== null && isset($calledLookup[$cellNumber]);
            }
        }

        return $marked;
    }

    /**
     * Check if a specific pattern is completely satisfied on the marked grid.
     *
     * @param  WinningPattern|array{coordinates: list<array{0: int, 1: int}>}  $pattern
     * @param  array<int, array<int, bool>>  $markedGrid
     */
    public function isPatternCompleted(WinningPattern|array $pattern, array $markedGrid): bool
    {
        $coordinates = $pattern instanceof WinningPattern ? $pattern->coordinates : ($pattern['coordinates'] ?? []);

        if (empty($coordinates)) {
            return false;
        }

        foreach ($coordinates as $coord) {
            $r = $coord[0];
            $c = $coord[1];

            if (! isset($markedGrid[$r][$c]) || ! $markedGrid[$r][$c]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Find all distinct completed patterns among the provided candidate patterns.
     *
     * @param  iterable<WinningPattern>  $patterns
     * @param  array<int, array<int, bool>>  $markedGrid
     * @return Collection<int, WinningPattern>
     */
    public function getCompletedPatterns(iterable $patterns, array $markedGrid): Collection
    {
        $completed = collect();

        foreach ($patterns as $pattern) {
            if ($this->isPatternCompleted($pattern, $markedGrid)) {
                $completed->push($pattern);
            }
        }

        // Ensure distinct patterns by slug or id
        return $completed->unique('slug')->values();
    }

    /**
     * Count the number of distinct completed patterns.
     *
     * @param  iterable<WinningPattern>  $patterns
     * @param  array<int, array<int, bool>>  $markedGrid
     */
    public function countCompletedPatterns(iterable $patterns, array $markedGrid): int
    {
        return $this->getCompletedPatterns($patterns, $markedGrid)->count();
    }

    /**
     * Evaluate the full winning condition for a card, called numbers, and game rules.
     *
     * @param  iterable<WinningPattern>  $allowedPatterns
     * @param  array<int, array<int, int>>  $cardGrid
     * @param  list<int>  $calledNumbers
     * @return array{
     *     is_winner: bool,
     *     completed_count: int,
     *     required_count: int,
     *     completed_patterns: Collection<int, WinningPattern>,
     *     completed_slugs: list<string>,
     *     marked_grid: array<int, array<int, bool>>
     * }
     */
    public function evaluate(
        iterable $allowedPatterns,
        int $requiredPatternCount,
        array $cardGrid,
        array $calledNumbers
    ): array {
        $markedGrid = $this->getMarkedGrid($cardGrid, $calledNumbers);
        $completedPatterns = $this->getCompletedPatterns($allowedPatterns, $markedGrid);
        $completedCount = $completedPatterns->count();
        $isWinner = $completedCount >= $requiredPatternCount;

        return [
            'is_winner' => $isWinner,
            'completed_count' => $completedCount,
            'required_count' => $requiredPatternCount,
            'completed_patterns' => $completedPatterns,
            'completed_slugs' => $completedPatterns->pluck('slug')->all(),
            'marked_grid' => $markedGrid,
        ];
    }

    /**
     * Validate coordinate format for custom pattern creation.
     *
     * @param  list<mixed>  $coordinates
     * @return array{is_valid: bool, errors: list<string>, normalized_coordinates: list<array{0: int, 1: int}>}
     */
    public function validateCoordinates(array $coordinates): array
    {
        $errors = [];
        $normalized = [];
        $seen = [];

        if (empty($coordinates)) {
            $errors[] = 'A pattern must contain at least one cell coordinate.';

            return ['is_valid' => false, 'errors' => $errors, 'normalized_coordinates' => []];
        }

        foreach ($coordinates as $index => $coord) {
            if (! is_array($coord) || count($coord) !== 2) {
                $errors[] = "Coordinate at index {$index} must be a pair of [row, col] integers.";

                continue;
            }

            $r = (int) $coord[0];
            $c = (int) $coord[1];

            if ($r < 0 || $r > 4 || $c < 0 || $c > 4) {
                $errors[] = "Coordinate [{$r}, {$c}] is outside the 5x5 board bounds (0-4).";

                continue;
            }

            $key = "{$r},{$c}";
            if (isset($seen[$key])) {
                $errors[] = "Duplicate coordinate [{$r}, {$c}] found in pattern definition.";

                continue;
            }

            $seen[$key] = true;
            $normalized[] = [$r, $c];
        }

        return [
            'is_valid' => count($errors) === 0,
            'errors' => $errors,
            'normalized_coordinates' => count($errors) === 0 ? $normalized : [],
        ];
    }
}
