<?php

namespace Database\Seeders;

use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameTemplate;
use App\Domains\Patterns\Models\WinningPattern;
use Illuminate\Database\Seeder;

class GameTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $horizontalIds = WinningPattern::where('type', WinningPattern::TYPE_HORIZONTAL)->pluck('id')->all();
        $verticalIds = WinningPattern::where('type', WinningPattern::TYPE_VERTICAL)->pluck('id')->all();
        $diagonalIds = WinningPattern::where('type', WinningPattern::TYPE_DIAGONAL)->pluck('id')->all();
        $xId = WinningPattern::where('slug', 'x_pattern')->pluck('id')->all();
        $fullCardId = WinningPattern::where('slug', 'full_card')->pluck('id')->all();

        $linePatternIds = array_values(array_unique(array_merge($horizontalIds, $verticalIds, $diagonalIds)));

        $templates = [
            [
                'name' => 'Single Line',
                'slug' => 'single_line',
                'description' => 'Fast-paced game. First player to complete any single line (Horizontal, Vertical, or Diagonal) wins.',
                'pattern_mode' => 'single',
                'required_pattern_count' => 1,
                'allowed_pattern_ids' => $linePatternIds,
                'winner_policy' => Game::WINNER_POLICY_FIRST_VALID,
                'default_call_interval' => 5,
                'default_min_players' => 1,
                'default_max_players' => 100,
                'default_entry_fee' => 100, // $1.00
                'default_prize_configuration' => ['type' => 'fixed', 'amount' => 5000], // $50.00
            ],
            [
                'name' => 'Double Line',
                'slug' => 'double_line',
                'description' => 'Complete two distinct lines (Horizontal, Vertical, or Diagonal) to claim victory.',
                'pattern_mode' => 'multiple',
                'required_pattern_count' => 2,
                'allowed_pattern_ids' => $linePatternIds,
                'winner_policy' => Game::WINNER_POLICY_FIRST_VALID,
                'default_call_interval' => 5,
                'default_min_players' => 2,
                'default_max_players' => 100,
                'default_entry_fee' => 200, // $2.00
                'default_prize_configuration' => ['type' => 'fixed', 'amount' => 10000], // $100.00
            ],
            [
                'name' => 'Triple Line',
                'slug' => 'triple_line',
                'description' => 'High intensity match requiring three distinct completed lines (Horizontal, Vertical, or Diagonal).',
                'pattern_mode' => 'multiple',
                'required_pattern_count' => 3,
                'allowed_pattern_ids' => $linePatternIds,
                'winner_policy' => Game::WINNER_POLICY_FIRST_VALID,
                'default_call_interval' => 5,
                'default_min_players' => 2,
                'default_max_players' => 100,
                'default_entry_fee' => 300, // $3.00
                'default_prize_configuration' => ['type' => 'fixed', 'amount' => 15000],
            ],
            [
                'name' => 'Pattern Challenge',
                'slug' => 'pattern_challenge',
                'description' => 'Complete any 2 distinct lines (Horizontal, Vertical, or Diagonal).',
                'pattern_mode' => 'multiple',
                'required_pattern_count' => 2,
                'allowed_pattern_ids' => array_values(array_unique(array_merge($horizontalIds, $verticalIds, $diagonalIds))),
                'winner_policy' => Game::WINNER_POLICY_FIRST_VALID,
                'default_call_interval' => 5,
                'default_min_players' => 2,
                'default_max_players' => 100,
                'default_entry_fee' => 250,
                'default_prize_configuration' => ['type' => 'fixed', 'amount' => 12500],
            ],
            [
                'name' => 'X Bingo',
                'slug' => 'x_bingo',
                'description' => 'Both intersecting diagonals forming a large X through the center.',
                'pattern_mode' => 'single',
                'required_pattern_count' => 1,
                'allowed_pattern_ids' => $xId,
                'winner_policy' => Game::WINNER_POLICY_FIRST_VALID,
                'default_call_interval' => 4,
                'default_min_players' => 2,
                'default_max_players' => 100,
                'default_entry_fee' => 200,
                'default_prize_configuration' => ['type' => 'fixed', 'amount' => 10000],
            ],
            [
                'name' => 'Full Card (Coverall)',
                'slug' => 'full_card',
                'description' => 'The ultimate jackpot game. Cover all 25 numbers on your card.',
                'pattern_mode' => 'single',
                'required_pattern_count' => 1,
                'allowed_pattern_ids' => $fullCardId,
                'winner_policy' => Game::WINNER_POLICY_FIRST_VALID,
                'default_call_interval' => 4,
                'default_min_players' => 5,
                'default_max_players' => 200,
                'default_entry_fee' => 500, // $5.00
                'default_prize_configuration' => ['type' => 'fixed', 'amount' => 50000], // $500.00
            ],
        ];

        foreach ($templates as $tmpl) {
            GameTemplate::updateOrCreate(
                [
                    'company_id' => null,
                    'slug' => $tmpl['slug'],
                ],
                [
                    'name' => $tmpl['name'],
                    'description' => $tmpl['description'],
                    'pattern_mode' => $tmpl['pattern_mode'],
                    'required_pattern_count' => $tmpl['required_pattern_count'],
                    'allowed_pattern_ids' => $tmpl['allowed_pattern_ids'],
                    'winner_policy' => $tmpl['winner_policy'],
                    'default_call_interval' => $tmpl['default_call_interval'],
                    'default_min_players' => $tmpl['default_min_players'],
                    'default_max_players' => $tmpl['default_max_players'],
                    'default_entry_fee' => $tmpl['default_entry_fee'],
                    'default_prize_configuration' => $tmpl['default_prize_configuration'],
                    'is_active' => true,
                ]
            );
        }
    }
}
