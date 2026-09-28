<?php

namespace App\Console\Commands;

use App\Domains\Games\Models\Game;
use App\Domains\Games\Services\NumberCallingService;
use Illuminate\Console\Command;

class CallNextBingoNumberCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bingo:call {game_id : The ID of the active game}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Draw and call the next Bingo number for an active game.';

    /**
     * Execute the console command.
     */
    public function handle(NumberCallingService $caller): int
    {
        $gameId = (int) $this->argument('game_id');
        $game = Game::find($gameId);

        if (! $game) {
            $this->error("Game #{$gameId} not found.");

            return Command::FAILURE;
        }

        if (! $game->isActive()) {
            $this->error("Game #{$gameId} is not active (status: {$game->status}).");

            return Command::FAILURE;
        }

        $call = $caller->callNextNumber($game);

        if (! $call) {
            $this->warn("All 75 numbers have already been called for Game #{$gameId}.");

            return Command::SUCCESS;
        }

        $remaining = count($caller->getRemainingNumbers($game));

        $this->info("Called: {$call->displayCode()} (Sequence #{$call->sequence_index}/75) | Remaining: {$remaining}");

        return Command::SUCCESS;
    }
}
