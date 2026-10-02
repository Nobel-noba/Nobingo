<?php

namespace Tests\Feature;

use App\Domains\Auth\Models\Role;
use App\Domains\Tenancy\Models\Company;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlayerRegistrationAndForcedPasswordResetTest extends TestCase
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

    public function test_public_self_registration_is_disabled(): void
    {
        // GET /register should redirect to login
        $getResponse = $this->get('/register');
        $getResponse->assertRedirect('/login');

        // POST /register should redirect to login and not create users
        $postResponse = $this->post('/register', [
            'name' => 'Self Registered User',
            'email' => 'self@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $postResponse->assertRedirect('/login');

        $this->assertDatabaseMissing('users', [
            'email' => 'self@example.com',
        ]);
    }

    public function test_game_manager_can_provision_new_player_with_must_reset_password_flag(): void
    {
        $response = $this->actingAs($this->gameManager)
            ->from("/c/{$this->company->slug}/admin/players")
            ->post("/c/{$this->company->slug}/admin/players", [
                'name' => 'Dave Counter Player',
                'email' => 'dave.player@acme.test',
                'password' => 'TemporaryPass123!',
                'initial_deposit' => 25.00,
            ]);

        $response->assertRedirect("/c/{$this->company->slug}/admin/players");

        $player = User::where('email', 'dave.player@acme.test')->first();
        $this->assertNotNull($player);
        $this->assertEquals($this->company->id, $player->company_id);
        $this->assertTrue($player->hasRole(Role::PLAYER));
        $this->assertTrue($player->must_reset_password);
        $this->assertTrue(Hash::check('TemporaryPass123!', $player->password));
        $this->assertEquals(2500, $player->balance); // $25.00 in cents

        $this->assertDatabaseHas('transactions', [
            'company_id' => $this->company->id,
            'user_id' => $player->id,
            'amount' => 2500,
        ]);
    }

    public function test_player_with_must_reset_password_flag_is_forced_to_reset_password(): void
    {
        $player = User::factory()->create([
            'company_id' => $this->company->id,
            'must_reset_password' => true,
        ]);
        $player->assignRole(Role::PLAYER);

        // Attempting to access lobby should redirect to force-reset
        $response = $this->actingAs($player)->get("/c/{$this->company->slug}/lobby");
        $response->assertRedirect(route('password.force-reset'));

        // Can access the force reset view
        $resetViewResponse = $this->actingAs($player)->get(route('password.force-reset'));
        $resetViewResponse->assertStatus(200);

        // Completing the forced password reset
        $postResetResponse = $this->actingAs($player)->post(route('password.force-reset.update'), [
            'password' => 'MyPersonalSecurePass456!',
            'password_confirmation' => 'MyPersonalSecurePass456!',
        ]);

        $postResetResponse->assertRedirect(route('dashboard'));

        $player->refresh();
        $this->assertFalse($player->must_reset_password);
        $this->assertTrue(Hash::check('MyPersonalSecurePass456!', $player->password));

        // Now player can access lobby freely
        $lobbyResponse = $this->actingAs($player)->get("/c/{$this->company->slug}/lobby");
        $lobbyResponse->assertStatus(200);
    }

    public function test_manager_resetting_player_password_re_enables_must_reset_flag(): void
    {
        $player = User::where('email', 'player1@acme.test')->firstOrFail();
        $player->update(['must_reset_password' => false]);

        $response = $this->actingAs($this->gameManager)
            ->from("/c/{$this->company->slug}/admin/players")
            ->post("/c/{$this->company->slug}/admin/players/{$player->id}/reset-password", [
                'password' => 'ManagerAssignedTemp789!',
            ]);

        $response->assertRedirect("/c/{$this->company->slug}/admin/players");

        $player->refresh();
        $this->assertTrue($player->must_reset_password);
        $this->assertTrue(Hash::check('ManagerAssignedTemp789!', $player->password));

        // When player makes request, redirected to force-reset
        $lobbyResponse = $this->actingAs($player)->get("/c/{$this->company->slug}/lobby");
        $lobbyResponse->assertRedirect(route('password.force-reset'));
    }
}
