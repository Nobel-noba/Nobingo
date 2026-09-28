<?php

namespace App\Domains\Games\Models;

use App\Domains\Cards\Models\BingoCard;
use App\Domains\Cards\Models\BingoCardVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameCard extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'game_id',
        'user_id',
        'bingo_card_id',
        'bingo_card_version_id',
        'marked_positions',
        'assigned_at',
        'released_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'marked_positions' => 'array',
            'assigned_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    /**
     * The game this card is assigned in.
     *
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * The user holding this card.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The underlying fixed card asset from inventory.
     *
     * @return BelongsTo<BingoCard, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(BingoCard::class, 'bingo_card_id');
    }

    /**
     * The exact immutable version of the card used for this game.
     *
     * @return BelongsTo<BingoCardVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(BingoCardVersion::class, 'bingo_card_version_id');
    }

    /**
     * Get all marked coordinates, ensuring center FREE square [2, 2] is always included.
     *
     * @return list<array{0: int, 1: int}>
     */
    public function getMarkedPositions(): array
    {
        $positions = $this->marked_positions ?? [];

        // Ensure center FREE square is always included
        $hasCenter = false;
        foreach ($positions as $pos) {
            if ($pos[0] === 2 && $pos[1] === 2) {
                $hasCenter = true;
                break;
            }
        }

        if (! $hasCenter) {
            $positions[] = [2, 2];
        }

        return $positions;
    }

    /**
     * Check if a specific coordinate is marked.
     */
    public function isMarked(int $row, int $col): bool
    {
        if ($row === 2 && $col === 2) {
            return true;
        }

        foreach ($this->getMarkedPositions() as $pos) {
            if ($pos[0] === $row && $pos[1] === $col) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add a coordinate to marked positions.
     */
    public function markPosition(int $row, int $col): bool
    {
        if ($this->isMarked($row, $col)) {
            return false;
        }

        $positions = $this->getMarkedPositions();
        $positions[] = [$row, $col];
        $this->update(['marked_positions' => $positions]);

        return true;
    }
}
