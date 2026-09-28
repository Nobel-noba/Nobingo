<?php

namespace App\Domains\Games\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class GameCall extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'game_id',
        'sequence_index',
        'ball_number',
        'letter',
        'called_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence_index' => 'integer',
            'ball_number' => 'integer',
            'called_at' => 'datetime',
        ];
    }

    /**
     * The game this call belongs to.
     *
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Formatted string e.g. "B-12", "N-38".
     */
    public function displayCode(): string
    {
        return "{$this->letter}-{$this->ball_number}";
    }

    /**
     * Determine standard 75-ball Bingo letter for any number 1..75.
     */
    public static function getLetterForNumber(int $number): string
    {
        return match (true) {
            $number >= 1 && $number <= 15 => 'B',
            $number >= 16 && $number <= 30 => 'I',
            $number >= 31 && $number <= 45 => 'N',
            $number >= 46 && $number <= 60 => 'G',
            $number >= 61 && $number <= 75 => 'O',
            default => throw new InvalidArgumentException("Bingo number must be between 1 and 75. Given: {$number}"),
        };
    }
}
