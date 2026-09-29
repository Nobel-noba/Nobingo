<?php

namespace App\Domains\Winners\Events;

use App\Domains\Games\Models\Game;
use App\Domains\Winners\Models\GameWinner;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BingoClaimRejected implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game,
        public GameWinner $winner,
        public ?string $reason = null
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
        return 'bingo.claim.rejected';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $card = $this->winner->card?->card;
        $user = $this->winner->user;

        return [
            'game_id' => $this->game->id,
            'game_number' => $this->game->game_number,
            'winner_id' => $this->winner->id,
            'user_id' => $this->winner->user_id,
            'winner_name' => $user?->name ?? $this->winner->card?->guest_identifier ?? 'Walk-in Player',
            'card_id' => $this->winner->game_card_id,
            'card_number' => $card?->card_number ?? sprintf('#%06d', $this->winner->game_card_id),
            'reason' => $this->reason ?? 'Claim was rejected by host after card verification.',
        ];
    }
}
