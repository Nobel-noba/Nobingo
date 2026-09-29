<?php

namespace App\Domains\Audit\Models;

use App\Domains\Tenancy\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    public const ACTION_GAME_CREATED = 'GAME_CREATED';

    public const ACTION_GAME_STATUS_CHANGED = 'GAME_STATUS_CHANGED';

    public const ACTION_BALL_CALLED = 'BALL_CALLED';

    public const ACTION_BINGO_CLAIMED = 'BINGO_CLAIMED';

    public const ACTION_WINNER_DECLARED = 'WINNER_DECLARED';

    public const ACTION_PRIZE_DISTRIBUTED = 'PRIZE_DISTRIBUTED';

    public const ACTION_PLAYER_SUSPENDED = 'PLAYER_SUSPENDED';

    public const ACTION_PLAYER_ACTIVATED = 'PLAYER_ACTIVATED';

    public const ACTION_BALANCE_ADJUSTED = 'BALANCE_ADJUSTED';

    public const ACTION_TEMPLATE_CREATED = 'TEMPLATE_CREATED';

    public const ACTION_TEMPLATE_UPDATED = 'TEMPLATE_UPDATED';

    public const ACTION_CARD_GENERATED = 'CARD_GENERATED';

    public const ACTION_CARD_STATUS_CHANGED = 'CARD_STATUS_CHANGED';

    protected $fillable = [
        'company_id',
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'description',
        'details',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * The tenant company this audit event belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The user / operator who triggered this event.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Polymorphic reference to affected domain model.
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
