<?php

namespace App\Domains\Games\Models;

use App\Domains\Tenancy\Traits\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Game extends Model
{
    use BelongsToCompany, HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_STARTING = 'starting';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const WINNER_POLICY_FIRST_VALID = 'first_valid';

    public const WINNER_POLICY_SIMULTANEOUS = 'simultaneous';

    /**
     * Allowed state transitions map.
     */
    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_OPEN, self::STATUS_CANCELLED],
        self::STATUS_OPEN => [self::STATUS_STARTING, self::STATUS_ACTIVE, self::STATUS_CANCELLED],
        self::STATUS_STARTING => [self::STATUS_ACTIVE, self::STATUS_CANCELLED],
        self::STATUS_ACTIVE => [self::STATUS_PAUSED, self::STATUS_COMPLETED, self::STATUS_CANCELLED],
        self::STATUS_PAUSED => [self::STATUS_ACTIVE, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'game_template_id',
        'game_number',
        'name',
        'description',
        'status',
        'configuration_snapshot',
        'entry_fee',
        'currency',
        'min_players',
        'max_players',
        'scheduled_start_at',
        'started_at',
        'ended_at',
        'call_interval',
        'winner_policy',
        'prize_configuration',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'game_number' => 'integer',
            'configuration_snapshot' => 'array',
            'entry_fee' => 'integer',
            'min_players' => 'integer',
            'max_players' => 'integer',
            'scheduled_start_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'call_interval' => 'integer',
            'prize_configuration' => 'array',
        ];
    }

    /**
     * Template this game was instantiated from.
     *
     * @return BelongsTo<GameTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(GameTemplate::class, 'game_template_id');
    }

    /**
     * User who created/scheduled this game.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Players registered in this game.
     *
     * @return HasMany<GamePlayer, $this>
     */
    public function players(): HasMany
    {
        return $this->hasMany(GamePlayer::class);
    }

    /**
     * Fixed cards assigned for this game.
     *
     * @return HasMany<GameCard, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(GameCard::class);
    }

    /**
     * Users who are playing this game.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'game_players')
            ->withPivot('entry_fee_paid', 'joined_at')
            ->withTimestamps();
    }

    /**
     * All number calls made in this game, ordered by sequence.
     *
     * @return HasMany<GameCall, $this>
     */
    public function calls(): HasMany
    {
        return $this->hasMany(GameCall::class)->orderBy('sequence_index');
    }

    /**
     * The latest number call made in this game.
     *
     * @return HasOne<GameCall, $this>
     */
    public function lastCall(): HasOne
    {
        return $this->hasOne(GameCall::class)->latestOfMany('sequence_index');
    }

    /**
     * Formatted game number (e.g. Game #1001).
     */
    public function formattedGameNumber(): string
    {
        return sprintf('Game #%d', $this->game_number);
    }

    /**
     * Formatted entry fee.
     */
    public function formattedEntryFee(): string
    {
        if ($this->entry_fee === 0) {
            return 'Free Entry';
        }

        return sprintf('$%.2f', $this->entry_fee / 100);
    }

    /**
     * Verify whether a state transition is legal.
     */
    public function canTransitionTo(string $targetStatus): bool
    {
        $allowed = self::TRANSITIONS[$this->status] ?? [];

        return in_array($targetStatus, $allowed, true);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
