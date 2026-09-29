<?php

namespace Tests\Feature;

use App\Domains\Auth\Models\Role;
use App\Domains\Auth\Services\EmailVerificationService;
use App\Domains\Games\Models\Game;
use App\Domains\Games\Services\GameLifecycleService;
use App\Domains\Tenancy\Models\Company;
use App\Mail\CompanyAdminWelcomeMail;
use App\Mail\CompanyPasswordResetMail;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PlatformCompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $platformOwner;

    protected Company $company;

    protected User $companyAdmin;

    protected EmailVerificationService $emailService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->emailService = app(EmailVerificationService::class);

        $this->platformOwner = User::whereHas('roles', fn ($q) => $q->where('slug', Role::PLATFORM_OWNER))->firstOrFail();
        $this->company = Company::where('slug', 'acme-bingo')->firstOrFail();
        $this->companyAdmin = User::where('email', 'admin@acme.test')->firstOrFail();
    }

    public function test_platform_owner_can_view_companies_directory(): void
    {
        $response = $this->actingAs($this->platformOwner)->get('/platform/companies');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/Companies')
            ->has('companies')
        );
    }

    public function test_platform_owner_can_provision_company_with_admin_and_dispatches_welcome_email(): void
    {
        Mail::fake();

        $payload = [
            'name' => 'Grand Vegas Bingo Club',
            'slug' => 'grand-vegas',
            'tagline' => 'High Stakes Casino Bingo',
            'brand_color' => '#e11d48',
            'currency' => 'USD',
            'admin_name' => 'Vegas Administrator',
            'admin_email' => 'admin@grandvegas.test',
            'admin_password' => 'SecurePass123!',
            'admin_password_confirmation' => 'SecurePass123!',
        ];

        $response = $this->actingAs($this->platformOwner)->post('/platform/companies', $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('companies', ['slug' => 'grand-vegas', 'status' => 'active']);
        $this->assertDatabaseHas('users', ['email' => 'admin@grandvegas.test']);

        $createdAdmin = User::where('email', 'admin@grandvegas.test')->firstOrFail();
        $this->assertTrue($createdAdmin->hasRole(Role::COMPANY_ADMIN));
        $this->assertNull($createdAdmin->email_verified_at);

        Mail::assertSent(CompanyAdminWelcomeMail::class, function ($mail) use ($createdAdmin) {
            return $mail->hasTo($createdAdmin->email);
        });
    }

    public function test_platform_owner_can_view_individual_company_management_console(): void
    {
        $response = $this->actingAs($this->platformOwner)->get("/platform/companies/{$this->company->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/CompanyShow')
            ->has('company')
            ->has('admins')
            ->has('stats')
        );
    }

    public function test_platform_owner_can_update_company_settings(): void
    {
        $response = $this->actingAs($this->platformOwner)->patch("/platform/companies/{$this->company->id}/settings", [
            'name' => 'Acme Premium Bingo Club',
            'domain' => 'bingo.acme.com',
            'tagline' => 'Updated Premier Experience',
            'brand_color' => '#10b981',
            'currency' => 'EUR',
        ]);

        $response->assertRedirect();
        $this->company->refresh();

        $this->assertEquals('Acme Premium Bingo Club', $this->company->name);
        $this->assertEquals('bingo.acme.com', $this->company->domain);
        $this->assertEquals('EUR', $this->company->settings['currency']);
    }

    public function test_platform_owner_can_revoke_and_restore_company_access(): void
    {
        // 1. Revoke access -> status becomes suspended
        $response = $this->actingAs($this->platformOwner)->patch("/platform/companies/{$this->company->id}/status");
        $response->assertRedirect();
        $this->company->refresh();
        $this->assertEquals('suspended', $this->company->status);

        // 2. Company admin cannot access company routes when suspended
        $blockedResponse = $this->actingAs($this->companyAdmin)->get("/c/{$this->company->slug}/admin");
        $blockedResponse->assertStatus(403);

        // 3. Restore access -> status becomes active
        $response2 = $this->actingAs($this->platformOwner)->patch("/platform/companies/{$this->company->id}/status");
        $response2->assertRedirect();
        $this->company->refresh();
        $this->assertEquals('active', $this->company->status);

        // 4. Company admin can access again
        $activeResponse = $this->actingAs($this->companyAdmin)->get("/c/{$this->company->slug}/admin");
        $activeResponse->assertStatus(200);
    }

    public function test_platform_owner_can_directly_change_admin_password(): void
    {
        $response = $this->actingAs($this->platformOwner)->post(
            "/platform/companies/{$this->company->id}/users/{$this->companyAdmin->id}/password-direct",
            [
                'password' => 'NewOwnerSetPass999!',
                'password_confirmation' => 'NewOwnerSetPass999!',
            ]
        );

        $response->assertRedirect();
        $this->companyAdmin->refresh();

        $this->assertTrue(Hash::check('NewOwnerSetPass999!', $this->companyAdmin->password));
    }

    public function test_platform_owner_can_request_and_confirm_email_verified_password_reset(): void
    {
        Mail::fake();

        // 1. Request password reset OTP
        $response = $this->actingAs($this->platformOwner)->post(
            "/platform/companies/{$this->company->id}/users/{$this->companyAdmin->id}/request-password-reset"
        );
        $response->assertRedirect();

        $capturedOtp = null;
        Mail::assertSent(CompanyPasswordResetMail::class, function ($mail) use (&$capturedOtp) {
            $capturedOtp = $mail->otp;

            return true;
        });

        $this->assertNotNull($capturedOtp);
        $this->assertEquals(6, strlen($capturedOtp));

        // 2. Attempt confirm with wrong OTP
        $failResponse = $this->actingAs($this->platformOwner)->post(
            "/platform/companies/{$this->company->id}/users/{$this->companyAdmin->id}/confirm-password-reset",
            [
                'otp' => '000000',
                'password' => 'ResetPass2026!',
                'password_confirmation' => 'ResetPass2026!',
            ]
        );
        $failResponse->assertSessionHasErrors(['otp']);

        // 3. Confirm with valid OTP
        $successResponse = $this->actingAs($this->platformOwner)->post(
            "/platform/companies/{$this->company->id}/users/{$this->companyAdmin->id}/confirm-password-reset",
            [
                'otp' => $capturedOtp,
                'password' => 'ResetPass2026!',
                'password_confirmation' => 'ResetPass2026!',
            ]
        );
        $successResponse->assertRedirect();
        $this->companyAdmin->refresh();

        $this->assertTrue(Hash::check('ResetPass2026!', $this->companyAdmin->password));
    }

    public function test_game_state_transition_is_idempotent_and_does_not_fail_from_active_to_active(): void
    {
        $gameService = app(GameLifecycleService::class);

        $game = Game::create([
            'company_id' => $this->company->id,
            'game_number' => 999,
            'name' => 'Active Test Game',
            'status' => Game::STATUS_ACTIVE,
            'entry_fee' => 0,
            'currency' => 'USD',
            'min_players' => 1,
            'max_players' => 50,
            'configuration_snapshot' => [
                'template_name' => 'Default 75-Ball',
                'required_pattern_count' => 1,
            ],
            'created_by' => $this->companyAdmin->id,
        ]);

        // Attempting to transition from active to active should not throw an exception
        $gameService->transitionTo($game, Game::STATUS_ACTIVE);
        $this->assertEquals(Game::STATUS_ACTIVE, $game->status);

        // Via controller endpoint
        $response = $this->actingAs($this->companyAdmin)
            ->patch("/c/{$this->company->slug}/admin/games/{$game->id}/status", [
                'status' => Game::STATUS_ACTIVE,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('info');
    }

    public function test_non_platform_owner_cannot_access_platform_management(): void
    {
        $response = $this->actingAs($this->companyAdmin)->get('/platform/companies');
        $response->assertStatus(403);
    }
}
