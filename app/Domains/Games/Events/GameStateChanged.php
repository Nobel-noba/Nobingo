<?php

namespace App\Domains\Games\Events;

use App\Domains\Games\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameStateChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game,
        public ?string $previousStatus = null
    ) {}

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("company.{$this->game->company_id}.game.{$this->game->id}"),
            new PrivateChannel("company.{$this->game->company_id}.lobby"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'game.state.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'game_id' => $this->game->id,
            'game_number' => $this->game->game_number,
            'name' => $this->game->name,
            'status' => $this->game->status,
            'previous_status' => $this->previousStatus,
            'started_at' => $this->game->started_at?->toIso8601String(),
            'ended_at' => $this->game->ended_at?->toIso8601String(),
        ];
    }
}
