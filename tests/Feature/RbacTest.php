<?php

namespace Tests\Feature;

use App\Domains\Auth\Models\Permission;
use App\Domains\Auth\Models\Role;
use App\Domains\Tenancy\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_assigned_roles_and_permissions(): void
    {
        $role = Role::create(['name' => 'Company Admin', 'slug' => Role::COMPANY_ADMIN]);
        $permission = Permission::create(['name' => 'Manage Cards', 'slug' => 'manage_cards']);
        $role->permissions()->attach($permission);

        $user = User::factory()->create();
        $user->assignRole(Role::COMPANY_ADMIN);

        $this->assertTrue($user->hasRole(Role::COMPANY_ADMIN));
        $this->assertTrue($user->isCompanyAdmin());
        $this->assertTrue($user->hasPermission('manage_cards'));
        $this->assertFalse($user->isPlatformOwner());
    }

    public function test_platform_owner_implicitly_has_all_permissions(): void
    {
        $ownerRole = Role::create(['name' => 'Platform Owner', 'slug' => Role::PLATFORM_OWNER]);
        $user = User::factory()->create();
        $user->assignRole($ownerRole);

        $this->assertTrue($user->isPlatformOwner());
        $this->assertTrue($user->hasPermission('any_arbitrary_permission'));
    }

    public function test_non_platform_owner_is_denied_from_platform_dashboard(): void
    {
        $playerRole = Role::create(['name' => 'Player', 'slug' => Role::PLAYER]);
        $player = User::factory()->create();
        $player->assignRole($playerRole);

        $response = $this->actingAs($player)->get('/platform/dashboard');

        $response->assertStatus(403);
    }

    public function test_platform_owner_can_access_platform_dashboard(): void
    {
        $ownerRole = Role::create(['name' => 'Platform Owner', 'slug' => Role::PLATFORM_OWNER]);
        $owner = User::factory()->create();
        $owner->assignRole($ownerRole);

        $response = $this->actingAs($owner)->get('/platform/dashboard');

        $response->assertStatus(200);
    }

    public function test_platform_owner_can_provision_new_company_tenant(): void
    {
        $ownerRole = Role::create(['name' => 'Platform Owner', 'slug' => Role::PLATFORM_OWNER]);
        $owner = User::factory()->create();
        $owner->assignRole($ownerRole);

        $response = $this->actingAs($owner)->post('/platform/companies', [
            'name' => 'Golden Palace Bingo',
            'slug' => 'golden-palace',
            'tagline' => 'Luxury bingo experience',
            'brand_color' => '#eab308',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('companies', [
            'name' => 'Golden Palace Bingo',
            'slug' => 'golden-palace',
        ]);
    }

    public function test_dashboard_smart_redirects_based_on_role(): void
    {
        $company = Company::create(['name' => 'Mega Bingo', 'slug' => 'mega-bingo', 'status' => 'active']);

        // 1. Platform owner -> /platform/dashboard
        $owner = User::factory()->create();
        $owner->assignRole(Role::PLATFORM_OWNER);
        $this->actingAs($owner)->get('/dashboard')->assertRedirect(route('platform.dashboard'));

        // 2. Company admin -> /c/mega-bingo/admin
        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole(Role::COMPANY_ADMIN);
        $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('company.admin.dashboard', ['company' => 'mega-bingo']));

        // 3. Player -> /c/mega-bingo/dashboard
        $player = User::factory()->create(['company_id' => $company->id]);
        $player->assignRole(Role::PLAYER);
        $this->actingAs($player)->get('/dashboard')->assertRedirect(route('player.dashboard', ['company' => 'mega-bingo']));
    }
}
