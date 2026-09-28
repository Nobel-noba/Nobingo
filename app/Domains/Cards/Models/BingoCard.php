<?php

namespace App\Domains\Cards\Models;

use App\Domains\Cards\Services\BingoCardValidator;
use App\Domains\Games\Models\GameCard;
use App\Domains\Tenancy\Models\Company;
use App\Domains\Tenancy\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class BingoCard extends Model
{
    use BelongsToCompany, HasFactory;

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_IN_USE = 'in_use';

    public const STATUS_LOCKED = 'locked';

    public const STATUS_RETIRED = 'retired';

    public const STATUS_DISABLED = 'disabled';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'card_number',
        'status',
        'current_version_id',
        'card_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'card_number' => 'integer',
            'current_version_id' => 'integer',
        ];
    }

    /**
     * Formatted card number for display (e.g., #000247).
     */
    public function formattedCardNumber(): string
    {
        return sprintf('#%06d', $this->card_number);
    }

    /**
     * All historical versions of this card.
     *
     * @return HasMany<BingoCardVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(BingoCardVersion::class, 'bingo_card_id')->orderBy('version_number', 'asc');
    }

    /**
     * The active/current version of this card.
     *
     * @return BelongsTo<BingoCardVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(BingoCardVersion::class, 'current_version_id');
    }

    /**
     * Game assignments for this card.
     *
     * @return HasMany<GameCard, $this>
     */
    public function gameCards(): HasMany
    {
        return $this->hasMany(GameCard::class, 'bingo_card_id');
    }

    /**
     * Update card numbers by creating a new version.
     * Historical completed games will remain tied to their original version.
     *
     * @param  array<int, array<int, mixed>>  $newGrid
     */
    public function updateGrid(array $newGrid, ?int $createdBy = null, ?string $notes = null): BingoCardVersion
    {
        $validator = app(BingoCardValidator::class);
        $validation = $validator->validate($newGrid);

        if (! $validation['is_valid']) {
            throw new InvalidArgumentException('Invalid grid: '.implode('; ', $validation['errors']));
        }

        $normalized = $validation['normalized_grid'];
        $hash = $validator->computeHash($normalized);

        // Check if another card in this company already uses this exact number set
        $duplicate = self::where('company_id', $this->company_id)
            ->where('card_hash', $hash)
            ->where('id', '!=', $this->id)
            ->exists();

        if ($duplicate) {
            throw new InvalidArgumentException('Duplicate card: A card with this exact layout already exists in this company inventory.');
        }

        $nextVersion = ($this->currentVersion?->version_number ?? 0) + 1;

        $columns = [0 => [], 1 => [], 2 => [], 3 => [], 4 => []];
        for ($r = 0; $r < 5; $r++) {
            for ($c = 0; $c < 5; $c++) {
                $columns[$c][] = $normalized[$r][$c];
            }
        }

        $version = $this->versions()->create([
            'version_number' => $nextVersion,
            'card_hash' => $hash,
            'grid' => $normalized,
            'b_column' => $columns[0],
            'i_column' => $columns[1],
            'n_column' => $columns[2],
            'g_column' => $columns[3],
            'o_column' => $columns[4],
            'created_by' => $createdBy,
            'notes' => $notes,
        ]);

        $this->update([
            'current_version_id' => $version->id,
            'card_hash' => $hash,
        ]);

        return $version;
    }

    /**
     * Check if card is available for assignment in an upcoming game.
     */
    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    /**
     * Check if card has been retired from circulation.
     */
    public function isRetired(): bool
    {
        return $this->status === self::STATUS_RETIRED;
    }
}
