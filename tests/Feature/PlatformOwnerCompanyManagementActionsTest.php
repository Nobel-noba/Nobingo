<?php

namespace Tests\Feature;

use App\Domains\Auth\Models\Role;
use App\Domains\Tenancy\Models\Company;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformOwnerCompanyManagementActionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $platformOwner;

    protected Company $company;

    protected User $companyAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->platformOwner = User::whereHas('roles', fn ($q) => $q->where('slug', Role::PLATFORM_OWNER))->firstOrFail();
        $this->company = Company::where('slug', 'acme-bingo')->firstOrFail();
        $this->companyAdmin = User::where('email', 'admin@acme.test')->firstOrFail();
    }

    public function test_platform_owner_can_view_rented_company_overview_with_financials_and_audit(): void
    {
        $response = $this->actingAs($this->platformOwner)->get("/platform/companies/{$this->company->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/CompanyShow')
            ->has('company')
            ->has('admins')
            ->has('stats.total_pots')
            ->has('stats.total_house_gross')
            ->has('stats.credit_balance')
            ->has('recent_games')
            ->has('audit_logs')
        );
    }

    public function test_platform_owner_can_directly_topup_company_platform_credits(): void
    {
        $initialBalance = $this->company->credit_balance;

        $response = $this->actingAs($this->platformOwner)
            ->from("/platform/companies/{$this->company->id}")
            ->post("/platform/companies/{$this->company->id}/topup-credits", [
                'amount' => 150.00,
                'notes' => 'Promotional credit top-up by platform owner',
            ]);

        $response->assertRedirect("/platform/companies/{$this->company->id}");

        $this->company->refresh();
        $this->assertEquals($initialBalance + 15000, $this->company->credit_balance);

        $this->assertDatabaseHas('transactions', [
            'company_id' => $this->company->id,
            'amount' => 15000,
            'type' => 'CREDIT_PURCHASE',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company->id,
            'user_id' => $this->platformOwner->id,
            'action' => 'BALANCE_ADJUSTED',
        ]);
    }

    public function test_non_platform_owner_cannot_topup_company_credits(): void
    {
        $response = $this->actingAs($this->companyAdmin)->post("/platform/companies/{$this->company->id}/topup-credits", [
            'amount' => 100.00,
        ]);

        $response->assertStatus(403);
    }
}
