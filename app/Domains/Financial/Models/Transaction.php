<?php

namespace App\Domains\Financial\Models;

use App\Domains\Tenancy\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    use HasFactory;

    public const TYPE_DEPOSIT = 'DEPOSIT';

    public const TYPE_WITHDRAWAL = 'WITHDRAWAL';

    public const TYPE_ENTRY_FEE = 'ENTRY_FEE';

    public const TYPE_PRIZE = 'PRIZE';

    public const TYPE_REFUND = 'REFUND';

    public const TYPE_ADJUSTMENT = 'ADJUSTMENT';

    public const TYPE_CREDIT_PURCHASE = 'CREDIT_PURCHASE';

    public const TYPE_PLATFORM_FEE = 'PLATFORM_FEE';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id',
        'user_id',
        'game_manager_id',
        'type',
        'amount',
        'currency',
        'status',
        'balance_before',
        'balance_after',
        'reference_type',
        'reference_id',
        'reference_code',
        'description',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_before' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    /**
     * The company this transaction belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The user this transaction belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The game manager responsible for / associated with this transaction.
     */
    public function gameManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'game_manager_id');
    }

    /**
     * Polymorphic reference to source entity (Game, GameWinner, Deposit, etc.).
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Whether this transaction increases user wallet balance.
     */
    public function isCredit(): bool
    {
        return in_array($this->type, [
            self::TYPE_DEPOSIT,
            self::TYPE_PRIZE,
            self::TYPE_REFUND,
        ], true) || ($this->type === self::TYPE_ADJUSTMENT && $this->balance_after >= $this->balance_before);
    }

    /**
     * Whether this transaction decreases user wallet balance.
     */
    public function isDebit(): bool
    {
        return in_array($this->type, [
            self::TYPE_WITHDRAWAL,
            self::TYPE_ENTRY_FEE,
        ], true) || ($this->type === self::TYPE_ADJUSTMENT && $this->balance_after < $this->balance_before);
    }

    /**
     * Format amount in cents to human readable currency string.
     */
    public function formattedAmount(string $symbol = '$'): string
    {
        $prefix = $this->isCredit() ? '+' : ($this->isDebit() ? '-' : '');

        return sprintf('%s%s%.2f', $prefix, $symbol, $this->amount / 100);
    }

    /**
     * Format balance before transaction.
     */
    public function formattedBalanceBefore(string $symbol = '$'): string
    {
        return sprintf('%s%.2f', $symbol, $this->balance_before / 100);
    }

    /**
     * Format balance after transaction.
     */
    public function formattedBalanceAfter(string $symbol = '$'): string
    {
        return sprintf('%s%.2f', $symbol, $this->balance_after / 100);
    }
}
