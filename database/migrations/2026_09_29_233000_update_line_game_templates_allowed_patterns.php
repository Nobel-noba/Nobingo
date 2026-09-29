<?php

use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameTemplate;
use App\Domains\Patterns\Models\WinningPattern;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $linePatternIds = WinningPattern::whereIn('type', [
            WinningPattern::TYPE_HORIZONTAL,
            WinningPattern::TYPE_VERTICAL,
            WinningPattern::TYPE_DIAGONAL,
        ])->pluck('id')->values()->all();

        if (empty($linePatternIds)) {
            return;
        }

        // Update standard templates
        GameTemplate::whereIn('slug', ['single_line', 'double_line', 'triple_line'])
            ->update(['allowed_pattern_ids' => $linePatternIds]);

        // Update games with line template snapshots
        $games = Game::all();
        foreach ($games as $game) {
            $snapshot = $game->configuration_snapshot;
            if (! is_array($snapshot)) {
                continue;
            }

            $templateName = strtolower($snapshot['template_name'] ?? '');
            $templateSlug = strtolower($snapshot['template_slug'] ?? '');
            $patternMode = strtolower($snapshot['pattern_mode'] ?? '');

            if (
                in_array($templateSlug, ['single_line', 'double_line', 'triple_line'], true) ||
                str_contains($templateName, 'line') ||
                $patternMode === 'single'
            ) {
                $snapshot['allowed_pattern_ids'] = $linePatternIds;
                $game->update(['configuration_snapshot' => $snapshot]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
