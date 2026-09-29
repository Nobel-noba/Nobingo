<?php

namespace App\Domains\Tenancy\Models;

use App\Domains\Cards\Models\BingoCard;
use App\Domains\Financial\Models\DepositRequest;
use App\Domains\Financial\Models\PaymentAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'status',
        'credit_balance',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credit_balance' => 'integer',
            'settings' => 'array',
        ];
    }

    /**
     * Users associated with this company.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Fixed cards belonging to this company inventory.
     *
     * @return HasMany<BingoCard, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(BingoCard::class);
    }

    /**
     * Check if company status is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Retrieve a specific setting value.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Formatted credit balance for display.
     */
    public function formattedCreditBalance(): string
    {
        return '$'.number_format($this->credit_balance / 100, 2);
    }

    /**
     * Check if company has sufficient credit.
     */
    public function hasSufficientCredit(int $amount = 1): bool
    {
        return $this->credit_balance >= $amount;
    }

    /**
     * Increment credit balance.
     */
    public function credit(int $amount): void
    {
        $this->increment('credit_balance', $amount);
    }

    /**
     * Decrement credit balance.
     */
    public function debit(int $amount): void
    {
        $this->decrement('credit_balance', $amount);
    }

    /**
     * Payment accounts configured by this company.
     *
     * @return HasMany<PaymentAccount, $this>
     */
    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(PaymentAccount::class);
    }

    /**
     * Deposit requests for this company.
     *
     * @return HasMany<DepositRequest, $this>
     */
    public function depositRequests(): HasMany
    {
        return $this->hasMany(DepositRequest::class);
    }
}
