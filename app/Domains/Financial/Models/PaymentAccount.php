<?php

namespace App\Domains\Financial\Models;

use App\Domains\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'provider_name',
        'account_name',
        'account_number',
        'instructions',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The company this account belongs to (null for Platform Owner).
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope to platform-level accounts.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForPlatform(Builder $query): Builder
    {
        return $query->whereNull('company_id');
    }

    /**
     * Scope to company-specific accounts.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to active accounts.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
