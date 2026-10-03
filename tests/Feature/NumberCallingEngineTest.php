<?php

namespace Tests\Feature;

use App\Domains\Auth\Models\Role;
use App\Domains\Cards\Services\BingoCardGenerator;
use App\Domains\Cards\Services\BingoCardValidator;
use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameCall;
use App\Domains\Games\Models\GameTemplate;
use App\Domains\Games\Services\CardAssignmentService;
use App\Domains\Games\Services\GameLifecycleService;
use App\Domains\Games\Services\NumberCallingService;
use App\Domains\Tenancy\Models\Company;
use App\Models\User;
use Database\Seeders\GameTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WinningPatternSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class NumberCallingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected GameLifecycleService $lifecycleService;

    protected CardAssignmentService $assignmentService;

    protected NumberCallingService $callingService;

    protected BingoCardGenerator $cardGenerator;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RolePermissionSeeder::class,
            WinningPatternSeeder::class,
            GameTemplateSeeder::class,
        ]);

        $this->lifecycleService = new GameLifecycleService;
        $this->assignmentService = new CardAssignmentService;
        $this->callingService = new NumberCallingService;
        $this->cardGenerator = new BingoCardGenerator(new BingoCardValidator);

        $this->company = Company::where('slug', 'acme-bingo')->firstOrFail();
    }

    public function test_calling_numbers_requires_active_game_status(): void
    {
        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company, ['status' => Game::STATUS_DRAFT]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot call number for game in status: draft. Game must be active.');

        $this->callingService->callNextNumber($game);
    }

    public function test_calling_generates_valid_letter_and_sequential_index(): void
    {
        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company, ['status' => Game::STATUS_DRAFT]);

        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);
        $this->lifecycleService->transitionTo($game, Game::STATUS_ACTIVE);

        $call1 = $this->callingService->callNextNumber($game);

        $this->assertNotNull($call1);
        $this->assertSame(1, $call1->sequence_index);
        $this->assertGreaterThanOrEqual(1, $call1->ball_number);
        $this->assertLessThanOrEqual(75, $call1->ball_number);
        $this->assertSame(GameCall::getLetterForNumber($call1->ball_number), $call1->letter);

        $call2 = $this->callingService->callNextNumber($game);
        $this->assertNotNull($call2);
        $this->assertSame(2, $call2->sequence_index);
        $this->assertNotSame($call1->ball_number, $call2->ball_number);
    }

    public function test_calling_all_75_numbers_produces_no_duplicates_and_exact_consecutive_sequences(): void
    {
        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company, ['status' => Game::STATUS_DRAFT]);

        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);
        $this->lifecycleService->transitionTo($game, Game::STATUS_ACTIVE);

        $drawnNumbers = [];

        for ($seq = 1; $seq <= 75; $seq++) {
            $call = $this->callingService->callNextNumber($game);
            $this->assertNotNull($call);
            $this->assertSame($seq, $call->sequence_index);
            $this->assertNotContains($call->ball_number, $drawnNumbers);
            $drawnNumbers[] = $call->ball_number;
        }

        // All 75 numbers must be distinct and span 1..75
        $this->assertCount(75, $drawnNumbers);
        sort($drawnNumbers);
        $this->assertSame(range(1, 75), $drawnNumbers);

        // 76th call should return null
        $call76 = $this->callingService->callNextNumber($game);
        $this->assertNull($call76);

        // Remaining numbers must be empty
        $this->assertEmpty($this->callingService->getRemainingNumbers($game));
    }

    public function test_database_unique_constraints_prevent_duplicate_calls(): void
    {
        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company, ['status' => Game::STATUS_DRAFT]);

        GameCall::create([
            'game_id' => $game->id,
            'sequence_index' => 1,
            'ball_number' => 25,
            'letter' => 'I',
        ]);

        $this->expectException(QueryException::class);

        // Attempting to call ball 25 again in the same game violates unique constraint
        GameCall::create([
            'game_id' => $game->id,
            'sequence_index' => 2,
            'ball_number' => 25,
            'letter' => 'I',
        ]);
    }

    public function test_auto_daub_updates_card_marked_positions_when_matching_number_is_called(): void
    {
        $player = User::factory()->create([
            'company_id' => $this->company->id,
            'balance' => 1000,
        ]);
        $this->cardGenerator->generateBatch($this->company, 5);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company, ['status' => Game::STATUS_DRAFT]);
        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);

        $this->assignmentService->joinGame($game, $player);
        $gameCard = $this->assignmentService->assignCardToPlayer($game, $player);

        // Initially marked positions contain only the FREE center [2, 2]
        $this->assertEquals([[2, 2]], $gameCard->getMarkedPositions());

        $this->lifecycleService->transitionTo($game, Game::STATUS_ACTIVE);

        // Get a specific number from the player's card (e.g. top-left coordinate [0, 0])
        $grid = $gameCard->version->grid;
        $targetNumber = $grid[0][0];

        // Trigger auto daub for that number
        $markedCount = $this->callingService->autoDaubCardsForNumber($game, $targetNumber);
        $this->assertSame(1, $markedCount);

        $this->assertTrue($gameCard->fresh()->isMarked(0, 0));
        $this->assertTrue($gameCard->fresh()->isMarked(2, 2));
    }

    public function test_manual_daub_validates_cell_against_called_numbers(): void
    {
        $player = User::factory()->create([
            'company_id' => $this->company->id,
            'balance' => 1000,
        ]);
        $this->cardGenerator->generateBatch($this->company, 5);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company, ['status' => Game::STATUS_DRAFT]);
        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);
        $this->assignmentService->joinGame($game, $player);
        $gameCard = $this->assignmentService->assignCardToPlayer($game, $player);

        $grid = $gameCard->version->grid;
        $uncalledNumber = $grid[0][1];

        // Trying to daub an uncalled number should fail with validation exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Number {$uncalledNumber} has not been called in this game yet.");

        $this->callingService->manualDaub($gameCard, 0, 1);
    }

    public function test_manual_daub_succeeds_for_called_numbers_and_free_square(): void
    {
        $player = User::factory()->create([
            'company_id' => $this->company->id,
            'balance' => 1000,
        ]);
        $this->cardGenerator->generateBatch($this->company, 5);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company, ['status' => Game::STATUS_DRAFT]);
        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);
        $this->assignmentService->joinGame($game, $player);
        $gameCard = $this->assignmentService->assignCardToPlayer($game, $player);

        $grid = $gameCard->version->grid;
        $numberToCall = $grid[1][1];

        // Call the number
        GameCall::create([
            'game_id' => $game->id,
            'sequence_index' => 1,
            'ball_number' => $numberToCall,
            'letter' => GameCall::getLetterForNumber($numberToCall),
        ]);

        $this->assertTrue($this->callingService->manualDaub($gameCard, 1, 1));
        $this->assertTrue($gameCard->fresh()->isMarked(1, 1));
        $this->assertTrue($this->callingService->manualDaub($gameCard, 2, 2)); // FREE square
    }

    public function test_operator_can_call_next_number_via_api(): void
    {
        $admin = User::factory()->create(['company_id' => $this->company->id]);
        $adminRole = Role::where('slug', Role::COMPANY_ADMIN)->firstOrFail();
        $admin->roles()->attach($adminRole);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company, ['status' => Game::STATUS_DRAFT]);
        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);
        $this->lifecycleService->transitionTo($game, Game::STATUS_ACTIVE);

        $response = $this->actingAs($admin)
            ->postJson("/c/{$this->company->slug}/admin/games/{$game->id}/call-next");

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'call' => ['id', 'sequence_index', 'ball_number', 'letter'],
            'remaining_count',
            'master_board',
        ]);

        $this->assertDatabaseCount('game_calls', 1);
    }

    public function test_player_can_fetch_live_game_state_and_daub_via_http(): void
    {
        $player = User::factory()->create([
            'company_id' => $this->company->id,
            'balance' => 1000,
        ]);
        $this->cardGenerator->generateBatch($this->company, 5);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company, ['status' => Game::STATUS_DRAFT]);
        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);
        $this->assignmentService->joinGame($game, $player);
        $gameCard = $this->assignmentService->assignCardToPlayer($game, $player);
        $this->lifecycleService->transitionTo($game, Game::STATUS_ACTIVE);

        // Fetch state via GET
        $stateResponse = $this->actingAs($player)
            ->getJson("/c/{$this->company->slug}/game/{$game->id}/state");

        $stateResponse->assertOk();
        $stateResponse->assertJsonStructure([
            'game_status',
            'last_call',
            'recent_calls',
            'call_count',
            'remaining_count',
            'master_board',
            'marked_positions',
        ]);

        // Draw a number
        $call = $this->callingService->callNextNumber($game);
        $this->assertNotNull($call);

        // Daub FREE square via POST
        $daubResponse = $this->actingAs($player)
            ->postJson("/c/{$this->company->slug}/game/{$game->id}/cards/{$gameCard->id}/daub", [
                'row' => 2,
                'col' => 2,
            ]);

        $daubResponse->assertOk();
        $daubResponse->assertJson(['success' => true]);
    }

    public function test_tenant_isolation_game_calls_do_not_leak_across_companies(): void
    {
        $companyB = Company::where('slug', 'lucky-star')->firstOrFail();

        $adminA = User::factory()->create(['company_id' => $this->company->id]);
        $adminRole = Role::where('slug', Role::COMPANY_ADMIN)->firstOrFail();
        $adminA->roles()->attach($adminRole);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $gameB = $this->lifecycleService->createFromTemplate($template, $companyB, ['status' => Game::STATUS_DRAFT]);
        $this->lifecycleService->transitionTo($gameB, Game::STATUS_OPEN);
        $this->lifecycleService->transitionTo($gameB, Game::STATUS_ACTIVE);

        // Admin of Company A cannot call balls in Company B's game
        $response = $this->actingAs($adminA)
            ->postJson("/c/{$this->company->slug}/admin/games/{$gameB->id}/call-next");

        $response->assertStatus(404);
    }
}
