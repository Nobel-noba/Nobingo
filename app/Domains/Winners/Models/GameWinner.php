<?php

namespace App\Domains\Winners\Models;

use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameCard;
use App\Domains\Patterns\Models\WinningPattern;
use App\Domains\Tenancy\Traits\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameWinner extends Model
{
    use BelongsToCompany, HasFactory;

    public const CLAIM_TYPE_MANUAL = 'manual';

    public const CLAIM_TYPE_AUTOMATIC = 'automatic';

    public const PAYOUT_STATUS_PENDING = 'pending';

    public const PAYOUT_STATUS_PAID = 'paid';

    public const PAYOUT_STATUS_REJECTED = 'rejected';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'game_id',
        'game_card_id',
        'user_id',
        'winning_pattern_id',
        'winning_patterns_snapshot',
        'winning_call_sequence',
        'winning_ball_number',
        'claim_type',
        'payout_amount',
        'split_ratio',
        'payout_status',
        'claimed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'winning_patterns_snapshot' => 'array',
            'winning_call_sequence' => 'integer',
            'winning_ball_number' => 'integer',
            'payout_amount' => 'integer',
            'split_ratio' => 'float',
            'claimed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * @return BelongsTo<GameCard, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(GameCard::class, 'game_card_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<WinningPattern, $this>
     */
    public function pattern(): BelongsTo
    {
        return $this->belongsTo(WinningPattern::class, 'winning_pattern_id');
    }

    /**
     * Formatted payout in dollars (e.g. $50.00).
     */
    public function formattedPayout(): string
    {
        if ($this->payout_amount === 0) {
            return '$0.00';
        }

        return sprintf('$%.2f', $this->payout_amount / 100);
    }

    /**
     * Check if winner is a walk-in player.
     */
    public function isWalkIn(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Display name for winner.
     */
    public function winnerDisplayName(): string
    {
        return $this->user?->name ?? $this->card?->guest_identifier ?? 'Walk-in Cash Player';
    }
}
