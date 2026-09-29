<?php

namespace Tests\Feature;

use App\Domains\Auth\Models\Role;
use App\Domains\Cards\Services\BingoCardGenerator;
use App\Domains\Cards\Services\BingoCardValidator;
use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameCall;
use App\Domains\Games\Models\GameCard;
use App\Domains\Games\Models\GameTemplate;
use App\Domains\Games\Services\CardAssignmentService;
use App\Domains\Games\Services\GameLifecycleService;
use App\Domains\Tenancy\Models\Company;
use App\Domains\Winners\Events\BingoClaimRejected;
use App\Domains\Winners\Events\BingoClaimSubmitted;
use App\Domains\Winners\Events\GameWon;
use App\Domains\Winners\Models\GameWinner;
use App\Domains\Winners\Services\BingoVerificationService;
use App\Models\User;
use Database\Seeders\GameTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WinningPatternSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BingoClaimAndVerificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $adminUser;

    protected User $player;

    protected GameLifecycleService $lifecycleService;

    protected CardAssignmentService $assignmentService;

    protected BingoVerificationService $verificationService;

    protected BingoCardGenerator $cardGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            WinningPatternSeeder::class,
            GameTemplateSeeder::class,
        ]);

        $this->company = Company::where('slug', 'acme-bingo')->firstOrFail();
        $this->company->update(['credit_balance' => 50000]); // $500.00 credit

        $this->adminUser = User::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);
        $this->adminUser->assignRole(Role::COMPANY_ADMIN);

        $this->player = User::factory()->create([
            'company_id' => $this->company->id,
            'balance' => 5000,
            'status' => 'active',
        ]);
        $this->player->assignRole(Role::PLAYER);

        $this->lifecycleService = new GameLifecycleService;
        $this->assignmentService = new CardAssignmentService;
        $this->verificationService = app(BingoVerificationService::class);
        $this->cardGenerator = new BingoCardGenerator(new BingoCardValidator);
        $this->cardGenerator->generateBatch($this->company, 10);
    }

    public function test_single_line_game_accepts_vertical_column_as_valid_win(): void
    {
        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company);
        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);
        $this->assignmentService->joinGame($game, $this->player);
        $gameCard = GameCard::where('game_id', $game->id)->where('user_id', $this->player->id)->firstOrFail();
        $this->lifecycleService->transitionTo($game, Game::STATUS_ACTIVE);

        // Fetch numbers in Column 0 (Column B)
        $grid = $gameCard->version->grid;
        $col0Numbers = [$grid[0][0], $grid[1][0], $grid[2][0], $grid[3][0], $grid[4][0]];

        foreach ($col0Numbers as $idx => $num) {
            GameCall::create([
                'game_id' => $game->id,
                'sequence_index' => $idx + 1,
                'ball_number' => $num,
                'letter' => GameCall::getLetterForNumber($num),
                'called_at' => now(),
            ]);
        }

        $eval = $this->verificationService->verifyCard($game, $gameCard, 5);
        $this->assertTrue($eval['is_valid']);
        $this->assertGreaterThanOrEqual(1, $eval['completed_count']);
        $this->assertContains('vertical_col_b', $eval['completed_slugs']);
    }

    public function test_single_line_game_accepts_diagonal_line_as_valid_win(): void
    {
        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company);
        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);
        $this->assignmentService->joinGame($game, $this->player);
        $gameCard = GameCard::where('game_id', $game->id)->where('user_id', $this->player->id)->firstOrFail();
        $this->lifecycleService->transitionTo($game, Game::STATUS_ACTIVE);

        // Fetch numbers in Main Diagonal: [0,0], [1,1], [2,2] (free), [3,3], [4,4]
        $grid = $gameCard->version->grid;
        $diagNumbers = [$grid[0][0], $grid[1][1], $grid[3][3], $grid[4][4]];

        foreach ($diagNumbers as $idx => $num) {
            GameCall::create([
                'game_id' => $game->id,
                'sequence_index' => $idx + 1,
                'ball_number' => $num,
                'letter' => GameCall::getLetterForNumber($num),
                'called_at' => now(),
            ]);
        }

        $eval = $this->verificationService->verifyCard($game, $gameCard, 4);
        $this->assertTrue($eval['is_valid']);
        $this->assertGreaterThanOrEqual(1, $eval['completed_count']);
        $this->assertContains('main_diagonal', $eval['completed_slugs']);
    }

    public function test_player_claim_pauses_game_and_creates_pending_winner(): void
    {
        Event::fake([BingoClaimSubmitted::class]);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company);
        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);
        $this->assignmentService->joinGame($game, $this->player);
        $gameCard = GameCard::where('game_id', $game->id)->where('user_id', $this->player->id)->firstOrFail();
        $this->lifecycleService->transitionTo($game, Game::STATUS_ACTIVE);

        // Call row 0 numbers
        $grid = $gameCard->version->grid;
        foreach ($grid[0] as $idx => $num) {
            GameCall::create([
                'game_id' => $game->id,
                'sequence_index' => $idx + 1,
                'ball_number' => $num,
                'letter' => GameCall::getLetterForNumber($num),
                'called_at' => now(),
            ]);
        }

        $response = $this->actingAs($this->player)
            ->postJson("/c/{$this->company->slug}/game/{$game->id}/cards/{$gameCard->id}/claim-bingo");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'pending_verification',
        ]);

        // Game status must be transitioned to paused!
        $this->assertSame(Game::STATUS_PAUSED, $game->fresh()->status);

        // Winner must be pending verification
        $this->assertDatabaseHas('game_winners', [
            'game_id' => $game->id,
            'game_card_id' => $gameCard->id,
            'user_id' => $this->player->id,
            'payout_status' => GameWinner::PAYOUT_STATUS_PENDING,
        ]);

        Event::assertDispatched(BingoClaimSubmitted::class);
    }

    public function test_admin_confirms_valid_claim_and_finalizes_game(): void
    {
        Event::fake([GameWon::class]);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company);
        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);
        $this->assignmentService->joinGame($game, $this->player);
        $gameCard = GameCard::where('game_id', $game->id)->where('user_id', $this->player->id)->firstOrFail();
        $this->lifecycleService->transitionTo($game, Game::STATUS_ACTIVE);

        // Call numbers and submit claim
        $grid = $gameCard->version->grid;
        foreach ($grid[0] as $idx => $num) {
            GameCall::create([
                'game_id' => $game->id,
                'sequence_index' => $idx + 1,
                'ball_number' => $num,
                'letter' => GameCall::getLetterForNumber($num),
                'called_at' => now(),
            ]);
        }

        $this->actingAs($this->player)
            ->postJson("/c/{$this->company->slug}/game/{$game->id}/cards/{$gameCard->id}/claim-bingo");

        $winner = GameWinner::where('game_id', $game->id)->firstOrFail();
        $this->assertSame(GameWinner::PAYOUT_STATUS_PENDING, $winner->payout_status);

        $initialBalance = $this->player->fresh()->balance;

        // Admin confirms claim
        $confirmResponse = $this->actingAs($this->adminUser)
            ->postJson("/c/{$this->company->slug}/admin/games/{$game->id}/claims/{$winner->id}/confirm");

        $confirmResponse->assertStatus(200);
        $confirmResponse->assertJson(['success' => true]);

        // Winner status updated to paid
        $this->assertSame(GameWinner::PAYOUT_STATUS_PAID, $winner->fresh()->payout_status);

        // Game completed
        $this->assertSame(Game::STATUS_COMPLETED, $game->fresh()->status);

        // Player ledger credited
        $this->assertGreaterThan($initialBalance, $this->player->fresh()->balance);

        Event::assertDispatched(GameWon::class);
    }

    public function test_admin_rejects_false_claim_allowing_session_resume(): void
    {
        Event::fake([BingoClaimRejected::class]);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company);
        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);
        $this->assignmentService->joinGame($game, $this->player);
        $gameCard = GameCard::where('game_id', $game->id)->where('user_id', $this->player->id)->firstOrFail();
        $this->lifecycleService->transitionTo($game, Game::STATUS_ACTIVE);

        $call = GameCall::create([
            'game_id' => $game->id,
            'sequence_index' => 1,
            'ball_number' => $gameCard->version->grid[0][0],
            'letter' => 'B',
            'called_at' => now(),
        ]);

        // Create a manual pending winner
        $winner = GameWinner::create([
            'company_id' => $this->company->id,
            'game_id' => $game->id,
            'game_card_id' => $gameCard->id,
            'user_id' => $this->player->id,
            'winning_call_sequence' => 1,
            'winning_ball_number' => $call->ball_number,
            'winning_patterns_snapshot' => [],
            'payout_amount' => 5000,
            'split_ratio' => 1.0,
            'payout_status' => GameWinner::PAYOUT_STATUS_PENDING,
            'claimed_at' => now(),
        ]);

        $this->lifecycleService->pauseGame($game);

        // Admin rejects claim
        $rejectResponse = $this->actingAs($this->adminUser)
            ->postJson("/c/{$this->company->slug}/admin/games/{$game->id}/claims/{$winner->id}/reject", [
                'reason' => 'Player does not have complete line.',
            ]);

        $rejectResponse->assertStatus(200);
        $rejectResponse->assertJson(['success' => true]);

        $this->assertSame(GameWinner::PAYOUT_STATUS_REJECTED, $winner->fresh()->payout_status);

        // Host resumes game session
        $this->lifecycleService->resumeGame($game->fresh());
        $this->assertSame(Game::STATUS_ACTIVE, $game->fresh()->status);

        Event::assertDispatched(BingoClaimRejected::class);
    }

    public function test_admin_verifies_and_declares_walkin_winner(): void
    {
        Event::fake([GameWon::class]);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company);
        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);

        // Assign card to walk-in cash customer
        $walkInCard = $this->assignmentService->assignWalkInCard($game, 1, 'Table 7 - Guest');
        $this->lifecycleService->transitionTo($game, Game::STATUS_ACTIVE);

        // Call Row 0 of walk-in card
        $grid = $walkInCard->version->grid;
        foreach ($grid[0] as $idx => $num) {
            GameCall::create([
                'game_id' => $game->id,
                'sequence_index' => $idx + 1,
                'ball_number' => $num,
                'letter' => GameCall::getLetterForNumber($num),
                'called_at' => now(),
            ]);
        }

        // Admin inspects card
        $verifyResponse = $this->actingAs($this->adminUser)
            ->postJson("/c/{$this->company->slug}/admin/games/{$game->id}/verify-card", [
                'card_number' => 1,
            ]);

        $verifyResponse->assertStatus(200);
        $verifyResponse->assertJson([
            'is_valid' => true,
            'card_number' => 1,
            'is_walkin' => true,
        ]);

        // Admin declares walk-in winner
        $declareResponse = $this->actingAs($this->adminUser)
            ->postJson("/c/{$this->company->slug}/admin/games/{$game->id}/declare-walkin-winner", [
                'card_number' => 1,
            ]);

        $declareResponse->assertStatus(200);
        $declareResponse->assertJson(['success' => true]);

        // Game completed
        $this->assertSame(Game::STATUS_COMPLETED, $game->fresh()->status);

        // Walk-in winner recorded as paid
        $winner = GameWinner::where('game_id', $game->id)->firstOrFail();
        $this->assertNull($winner->user_id);
        $this->assertSame(GameWinner::PAYOUT_STATUS_PAID, $winner->payout_status);

        Event::assertDispatched(GameWon::class);
    }
}
