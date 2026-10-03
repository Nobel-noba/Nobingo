<?php

namespace Tests\Feature;

use App\Domains\Auth\Models\Role;
use App\Domains\Cards\Models\BingoCard;
use App\Domains\Cards\Services\BingoCardGenerator;
use App\Domains\Cards\Services\BingoCardValidator;
use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameCard;
use App\Domains\Games\Models\GamePlayer;
use App\Domains\Games\Models\GameTemplate;
use App\Domains\Games\Services\CardAssignmentService;
use App\Domains\Games\Services\GameLifecycleService;
use App\Domains\Tenancy\Models\Company;
use App\Models\User;
use Database\Seeders\GameTemplateSeeder;
use Database\Seeders\WinningPatternSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class GameEngineTest extends TestCase
{
    use RefreshDatabase;

    protected GameLifecycleService $lifecycleService;

    protected CardAssignmentService $assignmentService;

    protected BingoCardGenerator $cardGenerator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            WinningPatternSeeder::class,
            GameTemplateSeeder::class,
        ]);

        $this->lifecycleService = new GameLifecycleService;
        $this->assignmentService = new CardAssignmentService;
        $this->cardGenerator = new BingoCardGenerator(new BingoCardValidator);
    }

    public function test_game_creation_captures_immutable_configuration_snapshot(): void
    {
        $company = Company::create(['name' => 'Acme Bingo', 'slug' => 'acme-bingo', 'status' => 'active']);
        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();

        $game = $this->lifecycleService->createFromTemplate($template, $company, [
            'name' => 'Friday Night Single Line',
            'entry_fee' => 150, // $1.50 override
        ]);

        $this->assertInstanceOf(Game::class, $game);
        $this->assertSame(1001, $game->game_number);
        $this->assertSame(Game::STATUS_DRAFT, $game->status);
        $this->assertSame(150, $game->entry_fee);

        // Verify configuration snapshot
        $snapshot = $game->configuration_snapshot;
        $this->assertIsArray($snapshot);
        $this->assertSame($template->id, $snapshot['template_id']);
        $this->assertSame(1, $snapshot['required_pattern_count']);
        $this->assertSame('single', $snapshot['pattern_mode']);

        // Modifying the template in DB does NOT alter the existing game's snapshot
        $template->update(['required_pattern_count' => 3]);
        $this->assertSame(1, $game->fresh()->configuration_snapshot['required_pattern_count']);
    }

    public function test_game_lifecycle_valid_state_transitions(): void
    {
        $company = Company::create(['name' => 'Acme Bingo', 'slug' => 'acme-bingo', 'status' => 'active']);
        $template = GameTemplate::firstOrFail();

        $game = $this->lifecycleService->createFromTemplate($template, $company);
        $this->assertSame(Game::STATUS_DRAFT, $game->status);

        // DRAFT -> OPEN
        $this->lifecycleService->openGame($game);
        $this->assertSame(Game::STATUS_OPEN, $game->fresh()->status);

        // OPEN -> STARTING -> ACTIVE
        $this->lifecycleService->startGame($game);
        $this->assertSame(Game::STATUS_ACTIVE, $game->fresh()->status);
        $this->assertNotNull($game->fresh()->started_at);

        // ACTIVE -> PAUSED
        $this->lifecycleService->pauseGame($game);
        $this->assertSame(Game::STATUS_PAUSED, $game->fresh()->status);

        // PAUSED -> ACTIVE (Resume)
        $this->lifecycleService->resumeGame($game);
        $this->assertSame(Game::STATUS_ACTIVE, $game->fresh()->status);

        // ACTIVE -> COMPLETED
        $this->lifecycleService->completeGame($game);
        $this->assertSame(Game::STATUS_COMPLETED, $game->fresh()->status);
        $this->assertNotNull($game->fresh()->ended_at);
    }

    public function test_game_lifecycle_rejects_illegal_state_transitions(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $company = Company::create(['name' => 'Acme Bingo', 'slug' => 'acme-bingo', 'status' => 'active']);
        $template = GameTemplate::firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $company);

        // Direct transition from DRAFT to ACTIVE is illegal (must be OPEN first)
        $this->lifecycleService->transitionTo($game, Game::STATUS_ACTIVE);
    }

    public function test_player_can_join_open_game_and_receive_fixed_card(): void
    {
        $company = Company::create(['name' => 'Acme Bingo', 'slug' => 'acme-bingo', 'status' => 'active']);
        $template = GameTemplate::firstOrFail();

        // Generate 5 fixed cards in company inventory
        $this->cardGenerator->generateBatch($company, 5);

        // Create and open game
        $game = $this->lifecycleService->createFromTemplate($template, $company, [
            'entry_fee' => 100, // $1.00
        ]);
        $this->lifecycleService->openGame($game);

        // Create player with $10.00 balance (1000 cents)
        $player = User::factory()->create([
            'company_id' => $company->id,
            'balance' => 1000,
        ]);

        $gamePlayer = $this->assignmentService->joinGame($game, $player);

        $this->assertInstanceOf(GamePlayer::class, $gamePlayer);
        $this->assertSame(100, $gamePlayer->entry_fee_paid);
        $this->assertSame(900, $player->fresh()->balance); // Balance decremented

        // Upon join, player is not assigned a card by default
        $assignedCard = GameCard::where('game_id', $game->id)->where('user_id', $player->id)->first();
        $this->assertNull($assignedCard);

        // Card is explicitly assigned by Game Manager
        $gameCard = $this->assignmentService->assignCardToPlayer($game, $player);
        $this->assertInstanceOf(GameCard::class, $gameCard);
        $this->assertNotNull($gameCard->bingo_card_id);
        $this->assertSame(BingoCard::STATUS_ASSIGNED, $gameCard->card->fresh()->status);
    }

    public function test_same_card_cannot_be_assigned_twice_in_same_game(): void
    {
        $company = Company::create(['name' => 'Acme Bingo', 'slug' => 'acme-bingo', 'status' => 'active']);
        $template = GameTemplate::firstOrFail();

        // Generate only 1 card in inventory
        $singleCard = $this->cardGenerator->generateCard($company);

        $game = $this->lifecycleService->createFromTemplate($template, $company);
        $this->lifecycleService->openGame($game);

        $player1 = User::factory()->create(['company_id' => $company->id, 'balance' => 500]);
        $player2 = User::factory()->create(['company_id' => $company->id, 'balance' => 500]);

        // Player 1 joins successfully and is assigned the only card
        $this->assignmentService->joinGame($game, $player1);
        $this->assignmentService->assignCardToPlayer($game, $player1);

        // Player 2 joins successfully without card
        $this->assignmentService->joinGame($game, $player2);

        // Attempting to assign card to Player 2 fails because no cards remain in inventory
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No available cards in company inventory');

        $this->assignmentService->assignCardToPlayer($game, $player2);
    }

    public function test_card_is_released_when_game_completes_and_reusable_for_future_game(): void
    {
        $company = Company::create(['name' => 'Acme Bingo', 'slug' => 'acme-bingo', 'status' => 'active']);
        $template = GameTemplate::firstOrFail();

        // Generate 1 fixed card
        $card = $this->cardGenerator->generateCard($company);

        // Game 1
        $game1 = $this->lifecycleService->createFromTemplate($template, $company);
        $this->lifecycleService->openGame($game1);

        $player1 = User::factory()->create(['company_id' => $company->id, 'balance' => 500]);
        $this->assignmentService->joinGame($game1, $player1);
        $this->assignmentService->assignCardToPlayer($game1, $player1);

        $this->assertSame(BingoCard::STATUS_ASSIGNED, $card->fresh()->status);

        // Start and complete Game 1
        $this->lifecycleService->startGame($game1);
        $this->lifecycleService->completeGame($game1);

        // Card is now released back to available inventory
        $this->assertSame(BingoCard::STATUS_AVAILABLE, $card->fresh()->status);

        // Game 2 can now reuse the exact same card for a different player
        $game2 = $this->lifecycleService->createFromTemplate($template, $company);
        $this->lifecycleService->openGame($game2);

        $player2 = User::factory()->create(['company_id' => $company->id, 'balance' => 500]);
        $this->assignmentService->joinGame($game2, $player2);
        $this->assignmentService->assignCardToPlayer($game2, $player2);

        $this->assertSame(BingoCard::STATUS_ASSIGNED, $card->fresh()->status);

        // Historical record for Game 1 is still permanent
        $this->assertDatabaseHas('game_cards', ['game_id' => $game1->id, 'bingo_card_id' => $card->id]);
        $this->assertDatabaseHas('game_cards', ['game_id' => $game2->id, 'bingo_card_id' => $card->id]);
    }

    public function test_game_cannot_start_without_assigning_cards_to_all_joined_players(): void
    {
        $company = Company::create(['name' => 'Acme Bingo', 'slug' => 'acme-bingo', 'status' => 'active']);
        $template = GameTemplate::firstOrFail();
        $this->cardGenerator->generateBatch($company, 2);

        $game = $this->lifecycleService->createFromTemplate($template, $company);
        $this->lifecycleService->openGame($game);

        $player = User::factory()->create(['name' => 'Abebe', 'company_id' => $company->id, 'balance' => 500]);
        $this->assignmentService->joinGame($game, $player);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot start game: 1 player (Abebe) have not been assigned cards');

        $this->lifecycleService->startGame($game);
    }

    public function test_player_cannot_join_game_with_insufficient_balance(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient wallet balance');

        $company = Company::create(['name' => 'Acme Bingo', 'slug' => 'acme-bingo', 'status' => 'active']);
        $template = GameTemplate::firstOrFail();
        $this->cardGenerator->generateBatch($company, 2);

        $game = $this->lifecycleService->createFromTemplate($template, $company, [
            'entry_fee' => 500, // $5.00
        ]);
        $this->lifecycleService->openGame($game);

        $poorPlayer = User::factory()->create(['company_id' => $company->id, 'balance' => 100]); // only $1.00
        $this->assignmentService->joinGame($game, $poorPlayer);
    }

    public function test_player_and_admin_http_endpoints_for_games(): void
    {
        $adminRole = Role::create(['name' => 'Company Admin', 'slug' => Role::COMPANY_ADMIN]);
        $playerRole = Role::create(['name' => 'Player', 'slug' => Role::PLAYER]);

        $company = Company::create(['name' => 'Echo Bingo', 'slug' => 'echo-bingo', 'status' => 'active']);
        $template = GameTemplate::firstOrFail();
        $this->cardGenerator->generateBatch($company, 5);

        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->roles()->attach($adminRole);

        $player = User::factory()->create(['company_id' => $company->id, 'balance' => 2000]);
        $player->roles()->attach($playerRole);

        // Admin schedules a game
        $createResponse = $this->actingAs($admin)->post("/c/{$company->slug}/admin/games", [
            'game_template_id' => $template->id,
            'name' => 'Sunday Tournament',
            'entry_fee' => 200,
            'auto_open' => true,
        ]);
        $createResponse->assertRedirect();

        $game = Game::where('company_id', $company->id)->where('name', 'Sunday Tournament')->firstOrFail();
        $this->assertSame(Game::STATUS_OPEN, $game->status);

        // Player views lobby
        $lobbyResponse = $this->actingAs($player)->get("/c/{$company->slug}/lobby");
        $lobbyResponse->assertStatus(200);

        // Player joins game
        $joinResponse = $this->actingAs($player)->post("/c/{$company->slug}/games/{$game->id}/join");
        $joinResponse->assertRedirect(route('player.game.show', ['company' => $company->slug, 'game' => $game->id]));

        // Player enters game room
        $roomResponse = $this->actingAs($player)->get("/c/{$company->slug}/game/{$game->id}");
        $roomResponse->assertStatus(200);
    }
}
