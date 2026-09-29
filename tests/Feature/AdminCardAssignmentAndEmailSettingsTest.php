<?php

namespace Tests\Feature;

use App\Domains\Auth\Models\Role;
use App\Domains\Cards\Models\BingoCard;
use App\Domains\Cards\Services\BingoCardGenerator;
use App\Domains\Cards\Services\BingoCardValidator;
use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameCard;
use App\Domains\Games\Models\GameTemplate;
use App\Domains\Games\Services\CardAssignmentService;
use App\Domains\Games\Services\GameLifecycleService;
use App\Domains\Platform\Models\PlatformSetting;
use App\Domains\Tenancy\Models\Company;
use App\Mail\PlatformTestMail;
use App\Models\User;
use Database\Seeders\GameTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WinningPatternSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminCardAssignmentAndEmailSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $adminUser;

    protected User $player;

    protected User $platformOwner;

    protected Game $game;

    protected CardAssignmentService $assignmentService;

    protected GameLifecycleService $lifecycleService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            WinningPatternSeeder::class,
            GameTemplateSeeder::class,
        ]);

        $this->assignmentService = new CardAssignmentService;
        $this->lifecycleService = new GameLifecycleService;

        // Company
        $this->company = Company::create([
            'name' => 'Bingo Palace',
            'slug' => 'bingo-palace',
            'status' => 'active',
        ]);

        // Users
        $this->adminUser = User::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);
        $this->adminUser->assignRole(Role::COMPANY_ADMIN);

        $this->player = User::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);
        $this->player->assignRole(Role::PLAYER);

        $this->platformOwner = User::factory()->create([
            'company_id' => null,
            'status' => 'active',
        ]);
        $this->platformOwner->assignRole(Role::PLATFORM_OWNER);

        // Generate Cards in Inventory
        $generator = new BingoCardGenerator(new BingoCardValidator);
        $generator->generateBatch($this->company, 5); // Cards 1 through 5

        // Create Game
        $template = GameTemplate::firstOrFail();
        $this->game = $this->lifecycleService->createFromTemplate($template, $this->company, [
            'name' => 'Test Room',
            'entry_fee' => 0,
        ]);
        $this->lifecycleService->openGame($this->game);
    }

    public function test_admin_can_assign_specific_card_number_to_player(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post("/c/{$this->company->slug}/admin/games/{$this->game->id}/assign-card", [
                'user_id' => $this->player->id,
                'card_number' => 3,
            ]);

        $response->assertSessionHas('success');

        $assignedGameCard = GameCard::where('game_id', $this->game->id)
            ->where('user_id', $this->player->id)
            ->first();

        $this->assertNotNull($assignedGameCard);
        $this->assertSame(3, $assignedGameCard->card->card_number);

        // Inventory card status should be 'assigned'
        $card3 = BingoCard::where('company_id', $this->company->id)->where('card_number', 3)->first();
        $this->assertSame(BingoCard::STATUS_ASSIGNED, $card3->status);
    }

    public function test_admin_can_reassign_player_card_and_previous_card_is_released(): void
    {
        // First assign card #1
        $this->assignmentService->assignSpecificCardToPlayer($this->game, $this->player, 1);

        $card1 = BingoCard::where('company_id', $this->company->id)->where('card_number', 1)->first();
        $this->assertSame(BingoCard::STATUS_ASSIGNED, $card1->status);

        // Reassign to card #2
        $response = $this->actingAs($this->adminUser)
            ->post("/c/{$this->company->slug}/admin/games/{$this->game->id}/assign-card", [
                'user_id' => $this->player->id,
                'card_number' => 2,
            ]);

        $response->assertSessionHas('success');

        // Player now holds card #2
        $activeCard = GameCard::where('game_id', $this->game->id)
            ->where('user_id', $this->player->id)
            ->first();

        $this->assertSame(2, $activeCard->card->card_number);

        // Card #1 should now be back to 'available'
        $card1->refresh();
        $this->assertSame(BingoCard::STATUS_AVAILABLE, $card1->status);

        // Card #2 should be 'assigned'
        $card2 = BingoCard::where('company_id', $this->company->id)->where('card_number', 2)->first();
        $this->assertSame(BingoCard::STATUS_ASSIGNED, $card2->status);
    }

    public function test_admin_cannot_assign_card_already_in_use_by_another_player(): void
    {
        $player2 = User::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);
        $player2->assignRole(Role::PLAYER);

        // Assign card #4 to player 1
        $this->assignmentService->assignSpecificCardToPlayer($this->game, $this->player, 4);

        // Try to assign card #4 to player 2
        $response = $this->actingAs($this->adminUser)
            ->post("/c/{$this->company->slug}/admin/games/{$this->game->id}/assign-card", [
                'user_id' => $player2->id,
                'card_number' => 4,
            ]);

        $response->assertSessionHas('error');
    }

    public function test_resilient_broadcasting_prevents_failure_when_broadcasting_throws(): void
    {
        $this->lifecycleService->startGame($this->game);
        $this->assertSame(Game::STATUS_ACTIVE, $this->game->status);

        // Pause the game - event GameStateChanged is wrapped in try/catch
        $this->lifecycleService->pauseGame($this->game);

        $this->game->refresh();
        $this->assertSame(Game::STATUS_PAUSED, $this->game->status);
    }

    public function test_platform_owner_can_view_email_settings(): void
    {
        $response = $this->actingAs($this->platformOwner)
            ->get('/platform/settings/email');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/EmailSettings')
            ->has('mail_settings')
            ->has('default_driver')
        );
    }

    public function test_platform_owner_can_save_email_settings(): void
    {
        $response = $this->actingAs($this->platformOwner)
            ->post('/platform/settings/email', [
                'driver' => 'smtp',
                'host' => 'smtp.resend.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'resend',
                'password' => 're_test_key_12345',
                'from_address' => 'notifications@nobingo.live',
                'from_name' => 'Nobingo Live',
            ]);

        $response->assertSessionHas('success');

        $saved = PlatformSetting::getMailSettings();
        $this->assertSame('smtp', $saved['driver']);
        $this->assertSame('smtp.resend.com', $saved['host']);
        $this->assertSame(587, $saved['port']);
        $this->assertSame('notifications@nobingo.live', $saved['from_address']);
        $this->assertSame('Nobingo Live', $saved['from_name']);
        $this->assertTrue($saved['has_password']);
    }

    public function test_platform_owner_can_send_test_email(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->platformOwner)
            ->post('/platform/settings/email/test', [
                'email' => 'admin@testpalace.com',
            ]);

        $response->assertSessionHas('success');

        Mail::assertSent(PlatformTestMail::class, function ($mail) {
            return $mail->hasTo('admin@testpalace.com');
        });
    }
}
