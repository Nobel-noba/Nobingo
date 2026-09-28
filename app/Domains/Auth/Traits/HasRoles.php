<?php

namespace App\Domains\Auth\Traits;

use App\Domains\Auth\Models\Permission;
use App\Domains\Auth\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasRoles
{
    /**
     * The roles that belong to the user.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Check if user has a specific role or one of multiple roles.
     *
     * @param  string|array<string>  ...$roles
     */
    public function hasRole(...$roles): bool
    {
        $roleList = collect($roles)->flatten()->all();

        $userRoles = $this->relationLoaded('roles') ? $this->roles : $this->roles()->get();

        return $userRoles->contains(function (Role $role) use ($roleList): bool {
            return in_array($role->slug, $roleList, true);
        });
    }

    /**
     * Check if user has a specific permission through any of their roles.
     */
    public function hasPermission(string $permissionSlug): bool
    {
        if ($this->isPlatformOwner()) {
            return true;
        }

        return $this->roles->loadMissing('permissions')->flatMap(function (Role $role) {
            return $role->permissions;
        })->contains('slug', $permissionSlug);
    }

    /**
     * Assign a role to the user.
     */
    public function assignRole(Role|string $role): void
    {
        if (is_string($role)) {
            $role = Role::firstOrCreate(
                ['slug' => $role],
                ['name' => ucwords(str_replace('_', ' ', strtolower($role)))]
            );
        }

        if (! $this->roles()->where('roles.id', $role->id)->exists()) {
            $this->roles()->attach($role->id);
            $this->load('roles');
        }
    }

    /**
     * Remove a role from the user.
     */
    public function removeRole(Role|string $role): void
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->first();
            if (! $role) {
                return;
            }
        }

        $this->roles()->detach($role->id);
        $this->load('roles');
    }

    /**
     * Check if user is the Platform Owner.
     */
    public function isPlatformOwner(): bool
    {
        return $this->hasRole(Role::PLATFORM_OWNER);
    }

    /**
     * Check if user is a Company Admin.
     */
    public function isCompanyAdmin(): bool
    {
        return $this->hasRole(Role::COMPANY_ADMIN);
    }

    /**
     * Check if user is a Game Manager.
     */
    public function isGameManager(): bool
    {
        return $this->hasRole(Role::GAME_MANAGER);
    }

    /**
     * Check if user is a Player.
     */
    public function isPlayer(): bool
    {
        return $this->hasRole(Role::PLAYER);
    }

    /**
     * Check if user can manage a specific company.
     */
    public function canManageCompany(?int $companyId): bool
    {
        if ($this->isPlatformOwner()) {
            return true;
        }

        if ($companyId === null) {
            return false;
        }

        return $this->isCompanyAdmin() && $this->company_id === $companyId;
    }
}
