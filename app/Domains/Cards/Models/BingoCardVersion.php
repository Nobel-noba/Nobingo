<?php

namespace App\Domains\Cards\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BingoCardVersion extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'bingo_card_id',
        'version_number',
        'card_hash',
        'grid',
        'b_column',
        'i_column',
        'n_column',
        'g_column',
        'o_column',
        'created_by',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'grid' => 'array',
            'b_column' => 'array',
            'i_column' => 'array',
            'n_column' => 'array',
            'g_column' => 'array',
            'o_column' => 'array',
        ];
    }

    /**
     * The parent bingo card.
     *
     * @return BelongsTo<BingoCard, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(BingoCard::class, 'bingo_card_id');
    }

    /**
     * The user who created this card version.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
