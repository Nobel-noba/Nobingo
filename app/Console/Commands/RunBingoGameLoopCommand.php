<?php

namespace App\Console\Commands;

use App\Domains\Games\Models\Game;
use App\Domains\Games\Services\NumberCallingService;
use Illuminate\Console\Command;

class RunBingoGameLoopCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bingo:call-loop 
                            {game_id : The ID of the active game}
                            {--interval= : Seconds between calls (defaults to game configuration)}
                            {--max-calls= : Maximum number of calls before stopping}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the automated calling loop for an active game.';

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

        $interval = (int) ($this->option('interval') ?: $game->call_interval ?: 5);
        $maxCalls = $this->option('max-calls') ? (int) $this->option('max-calls') : 75;

        $this->info("Starting Bingo Caller Loop for Game #{$game->game_number} ({$game->name})");
        $this->info("Call Interval: {$interval}s | Max Calls in this run: {$maxCalls}");

        $callsMade = 0;

        while ($callsMade < $maxCalls) {
            $game->refresh();

            if (! $game->isActive()) {
                $this->warn("Game #{$game->game_number} is no longer active (status: {$game->status}). Halting loop.");
                break;
            }

            $call = $caller->callNextNumber($game);

            if (! $call) {
                $this->info('All numbers called or board completed. Halting loop.');
                break;
            }

            $callsMade++;
            $remaining = count($caller->getRemainingNumbers($game));

            $this->line(sprintf(
                '[%s] Ball #%02d: %s | Remaining: %d',
                now()->format('H:i:s'),
                $call->sequence_index,
                $call->displayCode(),
                $remaining
            ));

            if ($callsMade < $maxCalls && $remaining > 0) {
                sleep($interval);
            }
        }

        $this->info("Caller loop ended. Total calls made in this session: {$callsMade}.");

        return Command::SUCCESS;
    }
}
