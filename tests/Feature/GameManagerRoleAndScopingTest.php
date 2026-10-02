<?php

namespace Tests\Feature;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Tenancy\Models\Company;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameManagerRoleAndScopingTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $companyAdmin;

    protected User $gameManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->company = Company::where('slug', 'acme-bingo')->firstOrFail();
        $this->companyAdmin = User::where('email', 'admin@acme.test')->firstOrFail();
        $this->gameManager = User::where('email', 'manager@acme.test')->firstOrFail();
    }

    public function test_game_manager_can_access_shared_operator_routes(): void
    {
        // Dashboard
        $response = $this->actingAs($this->gameManager)->get("/c/{$this->company->slug}/admin");
        $response->assertStatus(200);

        // Games Directory
        $response = $this->actingAs($this->gameManager)->get("/c/{$this->company->slug}/admin/games");
        $response->assertStatus(200);

        // Player Directory
        $response = $this->actingAs($this->gameManager)->get("/c/{$this->company->slug}/admin/players");
        $response->assertStatus(200);

        // Cashier / Deposits
        $response = $this->actingAs($this->gameManager)->get("/c/{$this->company->slug}/admin/deposit-requests");
        $response->assertStatus(200);

        // Audit Logs
        $response = $this->actingAs($this->gameManager)->get("/c/{$this->company->slug}/admin/audit-logs");
        $response->assertStatus(200);
    }

    public function test_game_manager_is_forbidden_from_executive_routes(): void
    {
        // Game Managers CRUD
        $response = $this->actingAs($this->gameManager)->get("/c/{$this->company->slug}/admin/game-managers");
        $response->assertStatus(403);

        // Credits / Billing
        $response = $this->actingAs($this->gameManager)->get("/c/{$this->company->slug}/admin/credits");
        $response->assertStatus(403);

        // Payment Accounts
        $response = $this->actingAs($this->gameManager)->get("/c/{$this->company->slug}/admin/payment-accounts");
        $response->assertStatus(403);

        // Ledger
        $response = $this->actingAs($this->gameManager)->get("/c/{$this->company->slug}/admin/ledger");
        $response->assertStatus(403);

        // Fixed Cards
        $response = $this->actingAs($this->gameManager)->get("/c/{$this->company->slug}/admin/cards");
        $response->assertStatus(403);
    }

    public function test_company_admin_can_access_executive_routes(): void
    {
        $response = $this->actingAs($this->companyAdmin)->get("/c/{$this->company->slug}/admin/game-managers");
        $response->assertStatus(200);

        $response = $this->actingAs($this->companyAdmin)->get("/c/{$this->company->slug}/admin/credits");
        $response->assertStatus(200);
    }

    public function test_game_manager_audit_log_only_returns_own_records(): void
    {
        // Create an audit log for Company Admin
        AuditLog::create([
            'company_id' => $this->company->id,
            'user_id' => $this->companyAdmin->id,
            'action' => 'ADMIN_ACTION',
            'description' => 'Admin sensitive configuration change',
        ]);

        // Create an audit log for Game Manager
        AuditLog::create([
            'company_id' => $this->company->id,
            'user_id' => $this->gameManager->id,
            'action' => 'MANAGER_ACTION',
            'description' => 'Manager drew a ball',
        ]);

        // When Game Manager views audit logs
        $response = $this->actingAs($this->gameManager)->get("/c/{$this->company->slug}/admin/audit-logs");
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Company/AuditLogs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.action', 'MANAGER_ACTION')
        );

        // When Company Admin views audit logs, they can see all logs
        $adminResponse = $this->actingAs($this->companyAdmin)->get("/c/{$this->company->slug}/admin/audit-logs");
        $adminResponse->assertStatus(200);
        $adminResponse->assertInertia(fn ($page) => $page
            ->component('Company/AuditLogs/Index')
            ->has('logs.data', 2)
        );
    }

    public function test_game_manager_dashboard_hides_credit_balance_and_scopes_games(): void
    {
        $response = $this->actingAs($this->gameManager)->get("/c/{$this->company->slug}/admin");
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Company/Dashboard')
            ->where('stats.is_game_manager_view', true)
            ->where('stats.credit_balance', null)
        );
    }
}
