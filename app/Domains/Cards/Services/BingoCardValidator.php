<?php

namespace App\Domains\Cards\Services;

use InvalidArgumentException;

class BingoCardValidator
{
    public const FREE_VALUE = 0;

    /**
     * Ranges for 75-ball columns: [min, max]
     */
    public const COLUMN_RANGES = [
        0 => [1, 15],   // B
        1 => [16, 30],  // I
        2 => [31, 45],  // N
        3 => [46, 60],  // G
        4 => [61, 75],  // O
    ];

    /**
     * Validate a 5x5 bingo card grid.
     *
     * @param  array<int, array<int, mixed>>  $grid
     * @return array{is_valid: bool, errors: list<string>, normalized_grid: array<int, array<int, int>>}
     */
    public function validate(array $grid): array
    {
        $errors = [];
        $normalized = [];

        if (count($grid) !== 5) {
            $errors[] = 'Card must have exactly 5 rows.';

            return ['is_valid' => false, 'errors' => $errors, 'normalized_grid' => []];
        }

        $allNumbers = [];
        $columns = [[], [], [], [], []];

        for ($row = 0; $row < 5; $row++) {
            if (! isset($grid[$row]) || ! is_array($grid[$row]) || count($grid[$row]) !== 5) {
                $errors[] = "Row {$row} must contain exactly 5 cells.";

                continue;
            }

            for ($col = 0; $col < 5; $col++) {
                $cell = $grid[$row][$col];

                // Center position (Row 2, Col 2) is FREE
                if ($row === 2 && $col === 2) {
                    if ($cell !== 0 && $cell !== '0' && strtoupper((string) $cell) !== 'FREE') {
                        $errors[] = 'Center square [2,2] must be marked as FREE.';
                    }
                    $normalized[$row][$col] = self::FREE_VALUE;

                    continue;
                }

                if (! is_numeric($cell) || (int) $cell <= 0) {
                    $errors[] = "Cell at [{$row}, {$col}] must be a positive integer.";

                    continue;
                }

                $num = (int) $cell;
                $normalized[$row][$col] = $num;
                $allNumbers[] = $num;
                $columns[$col][] = $num;

                // Validate range for this column
                [$min, $max] = self::COLUMN_RANGES[$col];
                if ($num < $min || $num > $max) {
                    $colName = ['B', 'I', 'N', 'G', 'O'][$col];
                    $errors[] = "Number {$num} at [{$row}, {$col}] is out of range for column {$colName} ({$min}-{$max}).";
                }
            }
        }

        // Check for duplicate numbers within the card
        if (count($allNumbers) !== count(array_unique($allNumbers))) {
            $duplicates = array_diff_assoc($allNumbers, array_unique($allNumbers));
            $errors[] = 'Card contains duplicate numbers: '.implode(', ', array_unique($duplicates)).'.';
        }

        // Verify counts per column
        if (count($columns[0]) !== 5) {
            $errors[] = 'Column B must contain exactly 5 numbers.';
        }
        if (count($columns[1]) !== 5) {
            $errors[] = 'Column I must contain exactly 5 numbers.';
        }
        if (count($columns[2]) !== 4) {
            $errors[] = 'Column N must contain exactly 4 playable numbers.';
        }
        if (count($columns[3]) !== 5) {
            $errors[] = 'Column G must contain exactly 5 numbers.';
        }
        if (count($columns[4]) !== 5) {
            $errors[] = 'Column O must contain exactly 5 numbers.';
        }

        return [
            'is_valid' => count($errors) === 0,
            'errors' => $errors,
            'normalized_grid' => count($errors) === 0 ? $normalized : [],
        ];
    }

    /**
     * Check if a grid is strictly valid.
     */
    public function isValid(array $grid): bool
    {
        return $this->validate($grid)['is_valid'];
    }

    /**
     * Compute a deterministic canonical SHA-256 fingerprint for a card grid.
     *
     * @param  array<int, array<int, mixed>>  $grid
     */
    public function computeHash(array $grid): string
    {
        $validation = $this->validate($grid);
        if (! $validation['is_valid']) {
            throw new InvalidArgumentException('Cannot compute hash for an invalid bingo card: '.implode('; ', $validation['errors']));
        }

        $flatNumbers = [];
        foreach ($validation['normalized_grid'] as $row) {
            foreach ($row as $cell) {
                $flatNumbers[] = $cell;
            }
        }

        return hash('sha256', implode(',', $flatNumbers));
    }
}
