<?php

namespace Tests\Feature;

use App\Domains\Auth\Models\Role;
use App\Domains\Tenancy\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_be_created_with_custom_settings(): void
    {
        $company = Company::create([
            'name' => 'Diamond Bingo',
            'slug' => 'diamond-bingo',
            'status' => 'active',
            'settings' => [
                'brand_color' => '#10b981',
                'tagline' => 'Win big every night',
            ],
        ]);

        $this->assertDatabaseHas('companies', [
            'slug' => 'diamond-bingo',
            'status' => 'active',
        ]);
        $this->assertSame('#10b981', $company->getSetting('brand_color'));
        $this->assertTrue($company->isActive());
    }

    public function test_platform_owner_can_access_any_company_admin_dashboard(): void
    {
        $ownerRole = Role::create(['name' => 'Platform Owner', 'slug' => Role::PLATFORM_OWNER]);
        $owner = User::factory()->create(['company_id' => null]);
        $owner->roles()->attach($ownerRole);

        $company = Company::create([
            'name' => 'Alpha Bingo',
            'slug' => 'alpha-bingo',
            'status' => 'active',
        ]);

        $response = $this->actingAs($owner)->get("/c/{$company->slug}/admin");

        $response->assertStatus(200);
    }

    public function test_company_admin_can_access_their_own_company_dashboard(): void
    {
        $adminRole = Role::create(['name' => 'Company Admin', 'slug' => Role::COMPANY_ADMIN]);

        $companyA = Company::create(['name' => 'Company A', 'slug' => 'company-a', 'status' => 'active']);
        $adminA = User::factory()->create(['company_id' => $companyA->id]);
        $adminA->roles()->attach($adminRole);

        $response = $this->actingAs($adminA)->get("/c/{$companyA->slug}/admin");

        $response->assertStatus(200);
    }

    public function test_cross_tenant_isolation_prevents_admin_from_accessing_different_company(): void
    {
        $adminRole = Role::create(['name' => 'Company Admin', 'slug' => Role::COMPANY_ADMIN]);

        $companyA = Company::create(['name' => 'Company A', 'slug' => 'company-a', 'status' => 'active']);
        $companyB = Company::create(['name' => 'Company B', 'slug' => 'company-b', 'status' => 'active']);

        $adminA = User::factory()->create(['company_id' => $companyA->id]);
        $adminA->roles()->attach($adminRole);

        // Admin A attempts to access Company B
        $response = $this->actingAs($adminA)->get("/c/{$companyB->slug}/admin");

        $response->assertStatus(403);
    }

    public function test_suspended_company_routes_return_forbidden(): void
    {
        $playerRole = Role::create(['name' => 'Player', 'slug' => Role::PLAYER]);

        $suspendedCompany = Company::create([
            'name' => 'Suspended Hall',
            'slug' => 'suspended-hall',
            'status' => 'suspended',
        ]);

        $player = User::factory()->create(['company_id' => $suspendedCompany->id]);
        $player->roles()->attach($playerRole);

        $response = $this->actingAs($player)->get("/c/{$suspendedCompany->slug}/dashboard");

        $response->assertStatus(403);
    }
}
