<?php

namespace App\Domains\Games\Events;

use App\Domains\Games\Models\Game;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerJoinedGame implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game,
        public User $user,
        public int $playersCount
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
        return 'player.joined';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'game_id' => $this->game->id,
            'user_id' => $this->user->id,
            'player_name' => $this->user->name,
            'players_count' => $this->playersCount,
            'joined_at' => now()->toIso8601String(),
        ];
    }
}
