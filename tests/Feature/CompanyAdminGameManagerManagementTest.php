<?php

namespace Tests\Feature;

use App\Domains\Auth\Models\Role;
use App\Domains\Tenancy\Models\Company;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CompanyAdminGameManagerManagementTest extends TestCase
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

    public function test_company_admin_can_view_game_managers_list(): void
    {
        $response = $this->actingAs($this->companyAdmin)->get("/c/{$this->company->slug}/admin/game-managers");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Company/GameManagers/Index')
            ->has('managers')
        );
    }

    public function test_company_admin_can_create_a_game_manager(): void
    {
        $response = $this->actingAs($this->companyAdmin)
            ->from("/c/{$this->company->slug}/admin/game-managers")
            ->post("/c/{$this->company->slug}/admin/game-managers", [
                'name' => 'John Live Caller',
                'email' => 'john.caller@acme.test',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $response->assertRedirect("/c/{$this->company->slug}/admin/game-managers");

        $newUser = User::where('email', 'john.caller@acme.test')->first();
        $this->assertNotNull($newUser);
        $this->assertEquals('John Live Caller', $newUser->name);
        $this->assertEquals($this->company->id, $newUser->company_id);
        $this->assertTrue($newUser->hasRole(Role::GAME_MANAGER));
        $this->assertTrue(Hash::check('Password123!', $newUser->password));

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company->id,
            'user_id' => $this->companyAdmin->id,
            'action' => 'PLAYER_ACTIVATED',
        ]);
    }

    public function test_company_admin_can_update_game_manager(): void
    {
        $response = $this->actingAs($this->companyAdmin)
            ->from("/c/{$this->company->slug}/admin/game-managers")
            ->patch("/c/{$this->company->slug}/admin/game-managers/{$this->gameManager->id}", [
                'name' => 'Updated Acme Caller Name',
                'email' => 'updated.caller@acme.test',
                'status' => 'suspended',
            ]);

        $response->assertRedirect("/c/{$this->company->slug}/admin/game-managers");

        $this->gameManager->refresh();
        $this->assertEquals('Updated Acme Caller Name', $this->gameManager->name);
        $this->assertEquals('updated.caller@acme.test', $this->gameManager->email);
        $this->assertEquals('suspended', $this->gameManager->status);
    }

    public function test_company_admin_can_reset_game_manager_password(): void
    {
        $response = $this->actingAs($this->companyAdmin)
            ->from("/c/{$this->company->slug}/admin/game-managers")
            ->post("/c/{$this->company->slug}/admin/game-managers/{$this->gameManager->id}/reset-password", [
                'password' => 'BrandNewPassword99!',
                'password_confirmation' => 'BrandNewPassword99!',
            ]);

        $response->assertRedirect("/c/{$this->company->slug}/admin/game-managers");

        $this->gameManager->refresh();
        $this->assertTrue(Hash::check('BrandNewPassword99!', $this->gameManager->password));
    }

    public function test_company_admin_can_delete_game_manager(): void
    {
        $response = $this->actingAs($this->companyAdmin)
            ->from("/c/{$this->company->slug}/admin/game-managers")
            ->delete("/c/{$this->company->slug}/admin/game-managers/{$this->gameManager->id}");

        $response->assertRedirect("/c/{$this->company->slug}/admin/game-managers");

        $this->assertDatabaseMissing('users', [
            'id' => $this->gameManager->id,
        ]);
    }
}
