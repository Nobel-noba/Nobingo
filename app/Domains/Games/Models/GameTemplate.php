<?php

namespace App\Domains\Games\Models;

use App\Domains\Patterns\Models\WinningPattern;
use App\Domains\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameTemplate extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'description',
        'pattern_mode',
        'required_pattern_count',
        'allowed_pattern_ids',
        'winner_policy',
        'default_call_interval',
        'default_min_players',
        'default_max_players',
        'default_entry_fee',
        'default_prize_configuration',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'required_pattern_count' => 'integer',
            'allowed_pattern_ids' => 'array',
            'default_call_interval' => 'integer',
            'default_min_players' => 'integer',
            'default_max_players' => 'integer',
            'default_entry_fee' => 'integer',
            'default_prize_configuration' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Company that owns this template (null if global template).
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Retrieve the WinningPattern models associated with this template.
     *
     * @return Collection<int, WinningPattern>
     */
    public function getPatterns(): Collection
    {
        $ids = $this->allowed_pattern_ids ?? [];
        if (empty($ids)) {
            return new Collection;
        }

        return WinningPattern::whereIn('id', $ids)->get();
    }

    /**
     * Scope to templates available for a company.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeAvailableForCompany(Builder $query, ?int $companyId = null): Builder
    {
        return $query->where(function ($q) use ($companyId) {
            $q->whereNull('company_id');
            if ($companyId !== null) {
                $q->orWhere('company_id', $companyId);
            }
        })->where('is_active', true);
    }

    /**
     * Scope to templates belonging to or global for a company (all statuses for admin management).
     *
     * @param  Builder<$this>  $query
     */
    public function scopeForCompany(Builder $query, ?int $companyId = null): Builder
    {
        return $query->where(function ($q) use ($companyId) {
            $q->whereNull('company_id');
            if ($companyId !== null) {
                $q->orWhere('company_id', $companyId);
            }
        });
    }
}
