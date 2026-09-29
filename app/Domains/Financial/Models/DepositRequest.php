<?php

namespace App\Domains\Financial\Models;

use App\Domains\Tenancy\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DepositRequest extends Model
{
    use HasFactory;

    public const TYPE_COMPANY_CREDIT = 'company_credit';

    public const TYPE_PLAYER_DEPOSIT = 'player_deposit';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'type',
        'company_id',
        'user_id',
        'payment_account_id',
        'amount',
        'currency',
        'reference_number',
        'receipt_path',
        'status',
        'notes',
        'reviewer_notes',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<PaymentAccount, $this>
     */
    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCompanyCredit(): bool
    {
        return $this->type === self::TYPE_COMPANY_CREDIT;
    }

    public function isPlayerDeposit(): bool
    {
        return $this->type === self::TYPE_PLAYER_DEPOSIT;
    }

    public function formattedAmount(): string
    {
        return '$'.number_format($this->amount / 100, 2);
    }

    public function receiptUrl(): string
    {
        return Storage::disk('public')->url($this->receipt_path);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeCompanyCredits(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_COMPANY_CREDIT);
    }

    public function scopeForCompanyCredit(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_COMPANY_CREDIT);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopePlayerDeposits(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_PLAYER_DEPOSIT);
    }

    public function scopeForPlayerDeposit(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_PLAYER_DEPOSIT);
    }
}
