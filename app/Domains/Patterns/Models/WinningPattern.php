<?php

namespace App\Domains\Patterns\Models;

use App\Domains\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WinningPattern extends Model
{
    use HasFactory;

    public const TYPE_HORIZONTAL = 'horizontal';

    public const TYPE_VERTICAL = 'vertical';

    public const TYPE_DIAGONAL = 'diagonal';

    public const TYPE_SPECIAL = 'special';

    public const TYPE_FULL_CARD = 'full_card';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'description',
        'type',
        'coordinates',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'coordinates' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The tenant company this pattern belongs to (null if global pattern).
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope to active patterns.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to patterns available to a specific company (global patterns + company custom patterns).
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
        });
    }

    /**
     * Count the total cells in this pattern.
     */
    public function cellCount(): int
    {
        return count($this->coordinates ?? []);
    }

    /**
     * Check if a specific [row, col] is part of this pattern.
     */
    public function hasCoordinate(int $row, int $col): bool
    {
        foreach ($this->coordinates ?? [] as $coord) {
            if ($coord[0] === $row && $coord[1] === $col) {
                return true;
            }
        }

        return false;
    }
}
