<?php

namespace App\Domains\Winners\Events;

use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameCall;
use App\Domains\Winners\Models\GameWinner;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BingoClaimSubmitted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game,
        public GameWinner $winner
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
        return 'bingo.claim.submitted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $card = $this->winner->card?->card;
        $user = $this->winner->user;

        $patternNames = [];
        if (! empty($this->winner->winning_patterns_snapshot)) {
            $patternNames = array_column($this->winner->winning_patterns_snapshot, 'name');
        }
        if (empty($patternNames) && $this->winner->pattern) {
            $patternNames = [$this->winner->pattern->name];
        }

        return [
            'game_id' => $this->game->id,
            'game_number' => $this->game->game_number,
            'winner_id' => $this->winner->id,
            'user_id' => $this->winner->user_id,
            'winner_name' => $user?->name ?? $this->winner->card?->guest_identifier ?? 'Walk-in Player',
            'is_walkin' => $this->winner->isWalkIn(),
            'card_id' => $this->winner->game_card_id,
            'card_number' => $card?->card_number ?? sprintf('#%06d', $this->winner->game_card_id),
            'patterns' => $patternNames,
            'winning_ball' => sprintf('%s-%d', GameCall::getLetterForNumber($this->winner->winning_ball_number), $this->winner->winning_ball_number),
            'winning_call_sequence' => $this->winner->winning_call_sequence,
            'winning_ball_number' => $this->winner->winning_ball_number,
            'payout_amount' => $this->winner->payout_amount,
            'formatted_payout' => $this->winner->formattedPayout(),
            'payout_status' => $this->winner->payout_status,
            'claimed_at' => $this->winner->claimed_at?->toIso8601String(),
        ];
    }
}
