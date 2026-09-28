<?php

namespace App\Domains\Games\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GamePlayer extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'game_id',
        'user_id',
        'entry_fee_paid',
        'joined_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_fee_paid' => 'integer',
            'joined_at' => 'datetime',
        ];
    }

    /**
     * The game joined.
     *
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * The player user.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The assigned fixed card in this game.
     *
     * @return HasOne<GameCard, $this>
     */
    public function assignedCard(): HasOne
    {
        return $this->hasOne(GameCard::class, 'user_id', 'user_id')
            ->where('game_id', $this->game_id);
    }
}
