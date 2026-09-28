<?php

namespace App\Domains\Games\Events;

use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameCall;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NumberCalled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game,
        public GameCall $call,
        public int $remainingCount,
        public int $markedCardsCount = 0
    ) {}

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("company.{$this->game->company_id}.game.{$this->game->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'number.called';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'game_id' => $this->game->id,
            'sequence_index' => $this->call->sequence_index,
            'ball_number' => $this->call->ball_number,
            'letter' => $this->call->letter,
            'code' => $this->call->displayCode(),
            'called_at' => $this->call->called_at?->toIso8601String(),
            'remaining_count' => $this->remainingCount,
            'marked_cards_count' => $this->markedCardsCount,
        ];
    }
}
