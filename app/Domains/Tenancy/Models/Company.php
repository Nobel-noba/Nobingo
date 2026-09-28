<?php

namespace App\Domains\Tenancy\Models;

use App\Domains\Cards\Models\BingoCard;
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
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
}
