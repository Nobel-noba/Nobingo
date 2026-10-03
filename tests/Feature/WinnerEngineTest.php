<?php

namespace Tests\Feature;

use App\Domains\Cards\Services\BingoCardGenerator;
use App\Domains\Cards\Services\BingoCardValidator;
use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameCall;
use App\Domains\Games\Models\GameTemplate;
use App\Domains\Games\Services\CardAssignmentService;
use App\Domains\Games\Services\GameLifecycleService;
use App\Domains\Games\Services\NumberCallingService;
use App\Domains\Tenancy\Models\Company;
use App\Domains\Winners\Events\GameWon;
use App\Domains\Winners\Models\GameWinner;
use App\Domains\Winners\Services\BingoVerificationService;
use App\Models\User;
use Database\Seeders\GameTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WinningPatternSeeder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WinnerEngineTest extends TestCase
{
    use RefreshDatabase;

    protected GameLifecycleService $lifecycleService;

    protected CardAssignmentService $assignmentService;

    protected NumberCallingService $callingService;

    protected BingoVerificationService $verificationService;

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
        $this->verificationService = app(BingoVerificationService::class);
        $this->cardGenerator = new BingoCardGenerator(new BingoCardValidator);

        $this->company = Company::where('slug', 'acme-bingo')->firstOrFail();
    }

    public function test_server_verifies_valid_bingo_claim_for_called_numbers(): void
    {
        $this->cardGenerator->generateBatch($this->company, 5);
        $player = User::factory()->create(['company_id' => $this->company->id, 'balance' => 1000]);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company);
        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);

        $this->assignmentService->joinGame($game, $player);
        $gameCard = $this->assignmentService->assignCardToPlayer($game, $player);
        $this->lifecycleService->transitionTo($game, Game::STATUS_ACTIVE);

        // Fetch row 0 numbers of assigned card
        $grid = $gameCard->version->grid;
        $row0Numbers = $grid[0]; // 5 numbers in top horizontal row

        // Authoritatively simulate calls for these 5 numbers
        foreach ($row0Numbers as $idx => $num) {
            GameCall::create([
                'game_id' => $game->id,
                'sequence_index' => $idx + 1,
                'ball_number' => $num,
                'letter' => GameCall::getLetterForNumber($num),
                'called_at' => now(),
            ]);
        }

        // Verify card directly via service
        $eval = $this->verificationService->verifyCard($game, $gameCard, 5);
        $this->assertTrue($eval['is_valid']);
        $this->assertGreaterThanOrEqual(1, $eval['completed_count']);

        // Submit claim via service
        $claimResult = $this->verificationService->claimBingo($game, $gameCard, $player);
        $this->assertTrue($claimResult['success']);
        $this->assertDatabaseHas('game_winners', [
            'game_id' => $game->id,
            'game_card_id' => $gameCard->id,
            'user_id' => $player->id,
            'winning_call_sequence' => 5,
        ]);

        // Game should be marked completed
        $this->assertSame(Game::STATUS_COMPLETED, $game->fresh()->status);
    }

    public function test_server_rejects_bingo_claim_if_card_pattern_not_complete(): void
    {
        $this->cardGenerator->generateBatch($this->company, 5);
        $player = User::factory()->create(['company_id' => $this->company->id, 'balance' => 1000]);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company);
        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);
        $this->assignmentService->joinGame($game, $player);
        $gameCard = $this->assignmentService->assignCardToPlayer($game, $player);
        $this->lifecycleService->transitionTo($game, Game::STATUS_ACTIVE);

        // Only call 2 numbers from row 0 (need 5 for a horizontal line)
        $grid = $gameCard->version->grid;
        GameCall::create([
            'game_id' => $game->id,
            'sequence_index' => 1,
            'ball_number' => $grid[0][0],
            'letter' => GameCall::getLetterForNumber($grid[0][0]),
            'called_at' => now(),
        ]);
        GameCall::create([
            'game_id' => $game->id,
            'sequence_index' => 2,
            'ball_number' => $grid[0][1],
            'letter' => GameCall::getLetterForNumber($grid[0][1]),
            'called_at' => now(),
        ]);

        $claimResult = $this->verificationService->claimBingo($game, $gameCard, $player);
        $this->assertFalse($claimResult['success']);
        $this->assertStringContainsString('pattern(s)', $claimResult['message']);
        $this->assertDatabaseMissing('game_winners', ['game_id' => $game->id]);
        $this->assertSame(Game::STATUS_ACTIVE, $game->fresh()->status);
    }

    public function test_server_rejects_claim_from_non_card_owner_or_non_participant(): void
    {
        $this->cardGenerator->generateBatch($this->company, 5);
        $player1 = User::factory()->create(['company_id' => $this->company->id, 'balance' => 1000]);
        $player2 = User::factory()->create(['company_id' => $this->company->id, 'balance' => 1000]);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company);
        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);
        $this->assignmentService->joinGame($game, $player1);
        $gameCard = $this->assignmentService->assignCardToPlayer($game, $player1);
        $this->lifecycleService->transitionTo($game, Game::STATUS_ACTIVE);

        // Simulate 5 calls completing player1's line
        foreach ($gameCard->version->grid[0] as $idx => $num) {
            GameCall::create([
                'game_id' => $game->id,
                'sequence_index' => $idx + 1,
                'ball_number' => $num,
                'letter' => GameCall::getLetterForNumber($num),
                'called_at' => now(),
            ]);
        }

        // Player 2 attempts to claim Player 1's card
        $claimResult = $this->verificationService->claimBingo($game, $gameCard, $player2);
        $this->assertFalse($claimResult['success']);
        $this->assertSame('Card does not belong to this player.', $claimResult['message']);
        $this->assertDatabaseMissing('game_winners', ['user_id' => $player2->id]);
    }

    public function test_server_rejects_claim_on_inactive_or_completed_game(): void
    {
        $this->cardGenerator->generateBatch($this->company, 5);
        $player = User::factory()->create(['company_id' => $this->company->id, 'balance' => 1000]);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company);
        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);
        $this->assignmentService->joinGame($game, $player);
        $gameCard = $this->assignmentService->assignCardToPlayer($game, $player);

        // Game is still OPEN, not ACTIVE
        $claimResult = $this->verificationService->claimBingo($game, $gameCard, $player);
        $this->assertFalse($claimResult['success']);
        $this->assertStringContainsString('Game is not active', $claimResult['message']);
    }

    public function test_first_valid_winner_policy_completes_game_and_blocks_subsequent_claims(): void
    {
        $this->cardGenerator->generateBatch($this->company, 10);
        $player1 = User::factory()->create(['company_id' => $this->company->id, 'balance' => 1000]);
        $player2 = User::factory()->create(['company_id' => $this->company->id, 'balance' => 1000]);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company, [
            'winner_policy' => Game::WINNER_POLICY_FIRST_VALID,
        ]);
        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);

        $this->assignmentService->joinGame($game, $player1);
        $card1 = $this->assignmentService->assignCardToPlayer($game, $player1);

        $this->assignmentService->joinGame($game, $player2);
        $card2 = $this->assignmentService->assignCardToPlayer($game, $player2);

        $this->lifecycleService->transitionTo($game, Game::STATUS_ACTIVE);

        // Complete line on card 1
        foreach ($card1->version->grid[0] as $idx => $num) {
            GameCall::create([
                'game_id' => $game->id,
                'sequence_index' => $idx + 1,
                'ball_number' => $num,
                'letter' => GameCall::getLetterForNumber($num),
                'called_at' => now(),
            ]);
        }

        // Player 1 claims first
        $result1 = $this->verificationService->claimBingo($game, $card1, $player1);
        $this->assertTrue($result1['success']);
        $this->assertSame(Game::STATUS_COMPLETED, $game->fresh()->status);

        // Player 2 attempts to claim afterward
        $result2 = $this->verificationService->claimBingo($game->fresh(), $card2, $player2);
        $this->assertFalse($result2['success']);
        $this->assertStringContainsString('Game is not active', $result2['message']);
        $this->assertSame(1, GameWinner::where('game_id', $game->id)->count());
    }

    public function test_simultaneous_winner_policy_splits_prize_on_same_ball_sequence(): void
    {
        $this->cardGenerator->generateBatch($this->company, 10);
        $player1 = User::factory()->create(['company_id' => $this->company->id, 'balance' => 1000]);
        $player2 = User::factory()->create(['company_id' => $this->company->id, 'balance' => 1000]);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company, [
            'winner_policy' => Game::WINNER_POLICY_SIMULTANEOUS,
            'prize_configuration' => ['fixed_prize' => 10000], // $100.00
        ]);
        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);

        $this->assignmentService->joinGame($game, $player1);
        $card1 = $this->assignmentService->assignCardToPlayer($game, $player1);

        $this->assignmentService->joinGame($game, $player2);
        $card2 = $this->assignmentService->assignCardToPlayer($game, $player2);

        $this->lifecycleService->transitionTo($game, Game::STATUS_ACTIVE);

        // Call all numbers from card1 row 0 AND card2 row 0
        $calls = array_unique(array_merge($card1->version->grid[0], $card2->version->grid[0]));
        foreach (array_values($calls) as $idx => $num) {
            GameCall::create([
                'game_id' => $game->id,
                'sequence_index' => $idx + 1,
                'ball_number' => $num,
                'letter' => GameCall::getLetterForNumber($num),
                'called_at' => now(),
            ]);
        }

        // Claim 1
        $res1 = $this->verificationService->claimBingo($game, $card1, $player1);
        $this->assertTrue($res1['success']);
        $this->assertSame(10000, $res1['winner']->payout_amount);
        $this->assertSame(1.0, (float) $res1['winner']->split_ratio);

        // Claim 2 on the exact same sequence!
        // To test tie handling, pass in the game with active status
        $game->update(['status' => Game::STATUS_ACTIVE]);
        $res2 = $this->verificationService->claimBingo($game, $card2, $player2);
        $this->assertTrue($res2['success']);

        // Check split
        $winners = GameWinner::where('game_id', $game->id)->orderBy('id')->get();
        $this->assertCount(2, $winners);
        $this->assertSame(5000, $winners[0]->payout_amount); // $50.00
        $this->assertSame(0.5, (float) $winners[0]->split_ratio);
        $this->assertSame(5000, $winners[1]->payout_amount); // $50.00
        $this->assertSame(0.5, (float) $winners[1]->split_ratio);
    }

    public function test_pessimistic_locking_prevents_duplicate_winner_records_for_same_card(): void
    {
        $this->cardGenerator->generateBatch($this->company, 5);
        $player = User::factory()->create(['company_id' => $this->company->id, 'balance' => 1000]);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company);
        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);
        $this->assignmentService->joinGame($game, $player);
        $card = $this->assignmentService->assignCardToPlayer($game, $player);
        $this->lifecycleService->transitionTo($game, Game::STATUS_ACTIVE);

        foreach ($card->version->grid[0] as $idx => $num) {
            GameCall::create([
                'game_id' => $game->id,
                'sequence_index' => $idx + 1,
                'ball_number' => $num,
                'letter' => GameCall::getLetterForNumber($num),
                'called_at' => now(),
            ]);
        }

        $res1 = $this->verificationService->claimBingo($game, $card, $player);
        $this->assertTrue($res1['success']);

        // Second duplicate claim on same card
        $game->update(['status' => Game::STATUS_ACTIVE]);
        $res2 = $this->verificationService->claimBingo($game, $card, $player);
        $this->assertFalse($res2['success']);
        $this->assertSame('This card has already claimed a win in this game.', $res2['message']);
        $this->assertSame(1, GameWinner::where('game_id', $game->id)->count());
    }

    public function test_game_won_event_broadcasts_on_game_and_lobby_channels(): void
    {
        $this->cardGenerator->generateBatch($this->company, 5);
        $player = User::factory()->create(['company_id' => $this->company->id, 'balance' => 1000]);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company);
        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);
        $this->assignmentService->joinGame($game, $player);
        $card = $this->assignmentService->assignCardToPlayer($game, $player);
        $this->lifecycleService->transitionTo($game, Game::STATUS_ACTIVE);

        foreach ($card->version->grid[0] as $idx => $num) {
            GameCall::create([
                'game_id' => $game->id,
                'sequence_index' => $idx + 1,
                'ball_number' => $num,
                'letter' => GameCall::getLetterForNumber($num),
                'called_at' => now(),
            ]);
        }

        Event::fake([GameWon::class]);

        $this->verificationService->claimBingo($game, $card, $player);

        Event::assertDispatched(GameWon::class, function ($e) use ($game, $player) {
            $channels = $e->broadcastOn();
            $this->assertCount(2, $channels);
            $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
            $this->assertSame("private-company.{$this->company->id}.game.{$game->id}", $channels[0]->name);
            $this->assertSame("private-company.{$this->company->id}.lobby", $channels[1]->name);
            $this->assertSame('game.won', $e->broadcastAs());

            $payload = $e->broadcastWith();
            $this->assertSame($game->id, $payload['game_id']);
            $this->assertSame($player->id, $payload['winner_id']);
            $this->assertTrue($payload['is_game_completed']);

            return true;
        });
    }

    public function test_automatic_bingo_detection_claims_win_upon_ball_call(): void
    {
        $this->cardGenerator->generateBatch($this->company, 5);
        $player = User::factory()->create(['company_id' => $this->company->id, 'balance' => 1000]);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company, [
            'auto_claim' => true,
        ]);
        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);
        $this->assignmentService->joinGame($game, $player);
        $card = $this->assignmentService->assignCardToPlayer($game, $player);
        $this->lifecycleService->transitionTo($game, Game::STATUS_ACTIVE);

        // Call the first 4 numbers of row 0
        $row0 = $card->version->grid[0];
        for ($i = 0; $i < 4; $i++) {
            GameCall::create([
                'game_id' => $game->id,
                'sequence_index' => $i + 1,
                'ball_number' => $row0[$i],
                'letter' => GameCall::getLetterForNumber($row0[$i]),
                'called_at' => now(),
            ]);
        }

        // Ensure no winner yet
        $this->assertSame(0, GameWinner::where('game_id', $game->id)->count());

        // Call the 5th number via checkAutomaticWinners
        $lastCall = GameCall::create([
            'game_id' => $game->id,
            'sequence_index' => 5,
            'ball_number' => $row0[4],
            'letter' => GameCall::getLetterForNumber($row0[4]),
            'called_at' => now(),
        ]);

        $autoWinners = $this->verificationService->checkAutomaticWinners($game, $lastCall);
        $this->assertCount(1, $autoWinners);
        $this->assertSame(GameWinner::CLAIM_TYPE_AUTOMATIC, $autoWinners->first()->claim_type);
        $this->assertSame(Game::STATUS_COMPLETED, $game->fresh()->status);
    }

    public function test_player_can_submit_bingo_claim_via_http_endpoint(): void
    {
        $this->cardGenerator->generateBatch($this->company, 5);
        $player = User::factory()->create(['company_id' => $this->company->id, 'balance' => 1000]);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company);
        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);
        $this->assignmentService->joinGame($game, $player);
        $card = $this->assignmentService->assignCardToPlayer($game, $player);
        $this->lifecycleService->transitionTo($game, Game::STATUS_ACTIVE);

        foreach ($card->version->grid[0] as $idx => $num) {
            GameCall::create([
                'game_id' => $game->id,
                'sequence_index' => $idx + 1,
                'ball_number' => $num,
                'letter' => GameCall::getLetterForNumber($num),
                'called_at' => now(),
            ]);
        }

        $response = $this->actingAs($player)
            ->postJson("/c/{$this->company->slug}/game/{$game->id}/cards/{$card->id}/claim-bingo");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('winner.user_id', $player->id);

        $this->assertDatabaseHas('game_winners', [
            'game_id' => $game->id,
            'user_id' => $player->id,
            'claim_type' => GameWinner::CLAIM_TYPE_MANUAL,
        ]);
    }

    public function test_multi_tenancy_isolation_prevents_cross_tenant_winner_access(): void
    {
        $companyB = Company::where('slug', 'lucky-star')->firstOrFail();
        $playerB = User::where('email', 'player2@luckystar.test')->firstOrFail();

        $this->cardGenerator->generateBatch($this->company, 5);
        $playerA = User::factory()->create(['company_id' => $this->company->id, 'balance' => 1000]);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company);
        $this->lifecycleService->transitionTo($game, Game::STATUS_OPEN);
        $this->assignmentService->joinGame($game, $playerA);
        $card = $this->assignmentService->assignCardToPlayer($game, $playerA);
        $this->lifecycleService->transitionTo($game, Game::STATUS_ACTIVE);

        // Player B attempts to claim on Company A's game
        $response = $this->actingAs($playerB)
            ->postJson("/c/{$companyB->slug}/game/{$game->id}/cards/{$card->id}/claim-bingo");

        $response->assertNotFound();
    }
}
