<?php

namespace App\Domains\Financial\Events;

use App\Domains\Games\Models\Game;
use App\Domains\Winners\Models\GameWinner;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrizeDistributed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Game $game,
        public GameWinner $winner,
        public int $amount
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('company.'.$this->game->company_id.'.game.'.$this->game->id),
            new Channel('company.'.$this->game->company_id.'.lobby'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'prize.distributed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'game_id' => $this->game->id,
            'winner_id' => $this->winner->id,
            'user_id' => $this->winner->user_id,
            'user_name' => $this->winner->user->name ?? 'Winner',
            'amount' => $this->amount,
            'formatted_amount' => '$'.number_format($this->amount / 100, 2),
            'distributed_at' => now()->toIso8601String(),
        ];
    }
}
