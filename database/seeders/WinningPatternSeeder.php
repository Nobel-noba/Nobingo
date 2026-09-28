<?php

namespace Database\Seeders;

use App\Domains\Patterns\Models\WinningPattern;
use Illuminate\Database\Seeder;

class WinningPatternSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $patterns = [
            // Horizontal Lines
            [
                'name' => 'Row 1 (Horizontal)',
                'slug' => 'horizontal_row_1',
                'description' => 'Complete horizontal line across the top row.',
                'type' => WinningPattern::TYPE_HORIZONTAL,
                'coordinates' => [[0, 0], [0, 1], [0, 2], [0, 3], [0, 4]],
            ],
            [
                'name' => 'Row 2 (Horizontal)',
                'slug' => 'horizontal_row_2',
                'description' => 'Complete horizontal line across the second row.',
                'type' => WinningPattern::TYPE_HORIZONTAL,
                'coordinates' => [[1, 0], [1, 1], [1, 2], [1, 3], [1, 4]],
            ],
            [
                'name' => 'Row 3 (Horizontal)',
                'slug' => 'horizontal_row_3',
                'description' => 'Complete horizontal line across the middle row (including FREE center).',
                'type' => WinningPattern::TYPE_HORIZONTAL,
                'coordinates' => [[2, 0], [2, 1], [2, 2], [2, 3], [2, 4]],
            ],
            [
                'name' => 'Row 4 (Horizontal)',
                'slug' => 'horizontal_row_4',
                'description' => 'Complete horizontal line across the fourth row.',
                'type' => WinningPattern::TYPE_HORIZONTAL,
                'coordinates' => [[3, 0], [3, 1], [3, 2], [3, 3], [3, 4]],
            ],
            [
                'name' => 'Row 5 (Horizontal)',
                'slug' => 'horizontal_row_5',
                'description' => 'Complete horizontal line across the bottom row.',
                'type' => WinningPattern::TYPE_HORIZONTAL,
                'coordinates' => [[4, 0], [4, 1], [4, 2], [4, 3], [4, 4]],
            ],

            // Vertical Lines
            [
                'name' => 'Column B (Vertical)',
                'slug' => 'vertical_col_b',
                'description' => 'Complete vertical line down the B column.',
                'type' => WinningPattern::TYPE_VERTICAL,
                'coordinates' => [[0, 0], [1, 0], [2, 0], [3, 0], [4, 0]],
            ],
            [
                'name' => 'Column I (Vertical)',
                'slug' => 'vertical_col_i',
                'description' => 'Complete vertical line down the I column.',
                'type' => WinningPattern::TYPE_VERTICAL,
                'coordinates' => [[0, 1], [1, 1], [2, 1], [3, 1], [4, 1]],
            ],
            [
                'name' => 'Column N (Vertical)',
                'slug' => 'vertical_col_n',
                'description' => 'Complete vertical line down the N column (including FREE center).',
                'type' => WinningPattern::TYPE_VERTICAL,
                'coordinates' => [[0, 2], [1, 2], [2, 2], [3, 2], [4, 2]],
            ],
            [
                'name' => 'Column G (Vertical)',
                'slug' => 'vertical_col_g',
                'description' => 'Complete vertical line down the G column.',
                'type' => WinningPattern::TYPE_VERTICAL,
                'coordinates' => [[0, 3], [1, 3], [2, 3], [3, 3], [4, 3]],
            ],
            [
                'name' => 'Column O (Vertical)',
                'slug' => 'vertical_col_o',
                'description' => 'Complete vertical line down the O column.',
                'type' => WinningPattern::TYPE_VERTICAL,
                'coordinates' => [[0, 4], [1, 4], [2, 4], [3, 4], [4, 4]],
            ],

            // Diagonals
            [
                'name' => 'Main Diagonal',
                'slug' => 'main_diagonal',
                'description' => 'Diagonal from top-left to bottom-right through center.',
                'type' => WinningPattern::TYPE_DIAGONAL,
                'coordinates' => [[0, 0], [1, 1], [2, 2], [3, 3], [4, 4]],
            ],
            [
                'name' => 'Reverse Diagonal',
                'slug' => 'reverse_diagonal',
                'description' => 'Diagonal from top-right to bottom-left through center.',
                'type' => WinningPattern::TYPE_DIAGONAL,
                'coordinates' => [[0, 4], [1, 3], [2, 2], [3, 1], [4, 0]],
            ],

            // Special Patterns
            [
                'name' => 'Four Corners',
                'slug' => 'four_corners',
                'description' => 'All four outer corners of the card.',
                'type' => WinningPattern::TYPE_SPECIAL,
                'coordinates' => [[0, 0], [0, 4], [4, 0], [4, 4]],
            ],
            [
                'name' => 'X Bingo',
                'slug' => 'x_pattern',
                'description' => 'Both diagonals intersecting through the center.',
                'type' => WinningPattern::TYPE_SPECIAL,
                'coordinates' => [
                    [0, 0], [0, 4],
                    [1, 1], [1, 3],
                    [2, 2],
                    [3, 1], [3, 3],
                    [4, 0], [4, 4],
                ],
            ],
            [
                'name' => 'Full Card (Coverall / Blackout)',
                'slug' => 'full_card',
                'description' => 'All 25 squares on the card marked.',
                'type' => WinningPattern::TYPE_FULL_CARD,
                'coordinates' => (function () {
                    $coords = [];
                    for ($r = 0; $r < 5; $r++) {
                        for ($c = 0; $c < 5; $c++) {
                            $coords[] = [$r, $c];
                        }
                    }

                    return $coords;
                })(),
            ],
        ];

        foreach ($patterns as $pattern) {
            WinningPattern::updateOrCreate(
                [
                    'company_id' => null,
                    'slug' => $pattern['slug'],
                ],
                [
                    'name' => $pattern['name'],
                    'description' => $pattern['description'],
                    'type' => $pattern['type'],
                    'coordinates' => $pattern['coordinates'],
                    'is_active' => true,
                ]
            );
        }
    }
}
