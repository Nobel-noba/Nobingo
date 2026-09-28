<?php

namespace App\Domains\Tenancy\Traits;

use App\Domains\Tenancy\Models\Company;
use App\Domains\Tenancy\Scopes\CompanyScope;
use App\Domains\Tenancy\Services\CompanyContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompany
{
    /**
     * Boot the BelongsToCompany trait.
     */
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model): void {
            if (empty($model->company_id)) {
                $context = app(CompanyContext::class);
                if ($context->hasCompany()) {
                    $model->company_id = $context->getId();
                }
            }
        });
    }

    /**
     * Get the company that owns this record.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
