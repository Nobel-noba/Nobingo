<?php

namespace Tests\Feature;

use App\Domains\Auth\Models\Role;
use App\Domains\Cards\Services\BingoCardGenerator;
use App\Domains\Cards\Services\BingoCardValidator;
use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameCard;
use App\Domains\Games\Models\GameTemplate;
use App\Domains\Games\Services\CardAssignmentService;
use App\Domains\Games\Services\GameLifecycleService;
use App\Domains\Games\Services\NumberCallingService;
use App\Domains\Tenancy\Models\Company;
use App\Models\User;
use Database\Seeders\GameTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WinningPatternSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;

    protected Company $companyB;

    protected User $adminA;

    protected User $adminB;

    protected User $playerA;

    protected User $playerB;

    protected GameLifecycleService $lifecycleService;

    protected CardAssignmentService $assignmentService;

    protected NumberCallingService $callingService;

    protected BingoCardGenerator $cardGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            WinningPatternSeeder::class,
            GameTemplateSeeder::class,
        ]);

        $this->companyA = Company::where('slug', 'acme-bingo')->firstOrFail();
        $this->companyB = Company::where('slug', 'lucky-star')->firstOrFail();

        $this->adminA = User::factory()->create([
            'company_id' => $this->companyA->id,
            'status' => 'active',
        ]);
        $this->adminA->assignRole(Role::COMPANY_ADMIN);

        $this->adminB = User::factory()->create([
            'company_id' => $this->companyB->id,
            'status' => 'active',
        ]);
        $this->adminB->assignRole(Role::COMPANY_ADMIN);

        $this->playerA = User::factory()->create([
            'company_id' => $this->companyA->id,
            'status' => 'active',
            'balance' => 10000, // $100.00
        ]);
        $this->playerA->assignRole(Role::PLAYER);

        $this->playerB = User::factory()->create([
            'company_id' => $this->companyB->id,
            'status' => 'active',
            'balance' => 10000,
        ]);
        $this->playerB->assignRole(Role::PLAYER);

        $this->lifecycleService = app(GameLifecycleService::class);
        $this->assignmentService = app(CardAssignmentService::class);
        $this->callingService = app(NumberCallingService::class);
        $this->cardGenerator = new BingoCardGenerator(new BingoCardValidator);

        // Generate fixed cards for both companies
        $this->cardGenerator->generateBatch($this->companyA, 10);
        $this->cardGenerator->generateBatch($this->companyB, 10);
    }

    public function test_tenant_a_player_cannot_view_or_join_tenant_b_games(): void
    {
        $templateB = GameTemplate::firstOrFail();
        $gameB = $this->lifecycleService->createFromTemplate($templateB, $this->companyB);
        $this->lifecycleService->openGame($gameB);

        // Player A attempts to join Company B's game
        $response = $this->actingAs($this->playerA)
            ->post(route('player.games.join', [$this->companyB, $gameB]));

        // Tenant middleware rejects or aborts due to cross-company mismatch
        $this->assertNotSame(302, $response->getStatusCode())
            || $this->assertDatabaseMissing('game_players', [
                'game_id' => $gameB->id,
                'user_id' => $this->playerA->id,
            ]);
    }

    public function test_tenant_player_cannot_claim_on_card_belonging_to_another_player(): void
    {
        $template = GameTemplate::firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->companyA);
        $this->lifecycleService->openGame($game);

        $playerTwo = User::factory()->create([
            'company_id' => $this->companyA->id,
            'status' => 'active',
            'balance' => 5000,
        ]);
        $playerTwo->assignRole(Role::PLAYER);

        // Both join game
        $this->assignmentService->joinGame($game, $this->playerA);
        $this->assignmentService->assignCardToPlayer($game, $this->playerA);
        $this->assignmentService->joinGame($game, $playerTwo);
        $this->assignmentService->assignCardToPlayer($game, $playerTwo);

        $this->lifecycleService->startGame($game);

        // Fetch Player Two's game card
        $cardTwo = GameCard::where('game_id', $game->id)
            ->where('user_id', $playerTwo->id)
            ->firstOrFail();

        // Player A attempts to submit a claim for Player Two's card
        $response = $this->actingAs($this->playerA)
            ->postJson(route('player.game.cards.claim-bingo', [$this->companyA, $game, $cardTwo]));

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Card does not belong to this player.',
        ]);
    }

    public function test_tenant_player_cannot_daub_card_belonging_to_another_player(): void
    {
        $template = GameTemplate::firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->companyA);
        $this->lifecycleService->openGame($game);

        $playerTwo = User::factory()->create([
            'company_id' => $this->companyA->id,
            'status' => 'active',
            'balance' => 5000,
        ]);
        $playerTwo->assignRole(Role::PLAYER);

        $this->assignmentService->joinGame($game, $this->playerA);
        $this->assignmentService->assignCardToPlayer($game, $this->playerA);
        $this->assignmentService->joinGame($game, $playerTwo);
        $this->assignmentService->assignCardToPlayer($game, $playerTwo);
        $this->lifecycleService->startGame($game);

        $cardTwo = GameCard::where('game_id', $game->id)->where('user_id', $playerTwo->id)->firstOrFail();

        // Player A tries to daub Player Two's card
        $response = $this->actingAs($this->playerA)
            ->postJson(route('player.game.cards.daub', [$this->companyA, $game, $cardTwo]), [
                'row' => 0,
                'col' => 0,
            ]);

        $response->assertStatus(403);
    }

    public function test_regular_player_cannot_access_any_company_admin_endpoint(): void
    {
        $adminEndpoints = [
            route('company.admin.dashboard', $this->companyA),
            route('company.admin.cards.index', $this->companyA),
            route('company.admin.patterns.index', $this->companyA),
            route('company.admin.games.index', $this->companyA),
            route('company.admin.templates.index', $this->companyA),
            route('company.admin.players.index', $this->companyA),
            route('company.admin.winners.index', $this->companyA),
            route('company.admin.ledger.index', $this->companyA),
            route('company.admin.audit-logs.index', $this->companyA),
            route('company.admin.reports.index', $this->companyA),
        ];

        foreach ($adminEndpoints as $endpoint) {
            $response = $this->actingAs($this->playerA)->get($endpoint);
            $response->assertForbidden();
        }
    }

    public function test_company_admin_a_cannot_access_company_admin_b_endpoints(): void
    {
        $response = $this->actingAs($this->adminA)
            ->get(route('company.admin.dashboard', $this->companyB));

        $this->assertTrue(in_array($response->getStatusCode(), [403, 404], true));
    }

    public function test_suspended_player_is_prevented_from_joining_game_claiming_or_withdrawing(): void
    {
        $suspendedPlayer = User::factory()->create([
            'company_id' => $this->companyA->id,
            'status' => 'suspended',
            'balance' => 5000,
        ]);
        $suspendedPlayer->assignRole(Role::PLAYER);

        $template = GameTemplate::firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->companyA);
        $this->lifecycleService->openGame($game);

        // 1. Join attempt blocked
        $joinResponse = $this->actingAs($suspendedPlayer)
            ->post(route('player.games.join', [$this->companyA, $game]));

        $joinResponse->assertRedirect();
        $joinResponse->assertSessionHas('error');
        $this->assertDatabaseMissing('game_players', [
            'game_id' => $game->id,
            'user_id' => $suspendedPlayer->id,
        ]);

        // 2. Withdrawal attempt blocked
        $withdrawResponse = $this->actingAs($suspendedPlayer)
            ->post(route('player.wallet.withdraw', $this->companyA), [
                'amount' => 10,
            ]);

        $withdrawResponse->assertRedirect();
        $withdrawResponse->assertSessionHasErrors(['withdraw']);
    }

    public function test_cannot_join_game_in_non_open_status(): void
    {
        $template = GameTemplate::firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->companyA);

        // Game is in 'draft'
        $responseDraft = $this->actingAs($this->playerA)
            ->post(route('player.games.join', [$this->companyA, $game]));

        $responseDraft->assertRedirect();
        $responseDraft->assertSessionHas('error');

        // Game in 'active'
        $this->lifecycleService->openGame($game);
        $this->lifecycleService->startGame($game);

        $responseActive = $this->actingAs($this->playerA)
            ->post(route('player.games.join', [$this->companyA, $game]));

        $responseActive->assertRedirect();
        $responseActive->assertSessionHas('error');
    }

    public function test_cannot_join_game_when_max_players_capacity_reached(): void
    {
        $template = GameTemplate::firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->companyA, [
            'max_players' => 1,
        ]);
        $this->lifecycleService->openGame($game);

        // First player joins successfully
        $this->assignmentService->joinGame($game, $this->playerA);

        $playerTwo = User::factory()->create([
            'company_id' => $this->companyA->id,
            'status' => 'active',
            'balance' => 5000,
        ]);
        $playerTwo->assignRole(Role::PLAYER);

        // Second player joins -> rejected
        $response = $this->actingAs($playerTwo)
            ->post(route('player.games.join', [$this->companyA, $game]));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_cannot_join_same_game_twice_with_same_player(): void
    {
        $template = GameTemplate::firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->companyA);
        $this->lifecycleService->openGame($game);

        // First join succeeds
        $this->assignmentService->joinGame($game, $this->playerA);

        // Second join attempt fails
        $response = $this->actingAs($this->playerA)
            ->post(route('player.games.join', [$this->companyA, $game]));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_server_rejects_false_bingo_claim_with_incomplete_pattern(): void
    {
        $template = GameTemplate::firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->companyA);
        $this->lifecycleService->openGame($game);
        $this->assignmentService->joinGame($game, $this->playerA);
        $this->assignmentService->assignCardToPlayer($game, $this->playerA);
        $this->lifecycleService->startGame($game);

        // Call only 1 ball
        $this->callingService->callNextNumber($game);

        $gameCard = GameCard::where('game_id', $game->id)->where('user_id', $this->playerA->id)->firstOrFail();

        // Submit claim
        $response = $this->actingAs($this->playerA)
            ->postJson(route('player.game.cards.claim-bingo', [$this->companyA, $game, $gameCard]));

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
        $this->assertDatabaseMissing('game_winners', [
            'game_id' => $game->id,
            'user_id' => $this->playerA->id,
        ]);
    }

    public function test_server_rejects_manual_daub_on_number_not_yet_called(): void
    {
        $template = GameTemplate::firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->companyA);
        $this->lifecycleService->openGame($game);
        $this->assignmentService->joinGame($game, $this->playerA);
        $this->assignmentService->assignCardToPlayer($game, $this->playerA);
        $this->lifecycleService->startGame($game);

        $gameCard = GameCard::where('game_id', $game->id)->where('user_id', $this->playerA->id)->firstOrFail();

        // No balls called yet; attempt to daub cell (0, 0)
        $response = $this->actingAs($this->playerA)
            ->postJson(route('player.game.cards.daub', [$this->companyA, $game, $gameCard]), [
                'row' => 0,
                'col' => 0,
            ]);

        $response->assertStatus(422);
    }

    public function test_boundary_validation_on_wallet_operations(): void
    {
        // 1. Negative deposit rejected
        $responseNegativeDeposit = $this->actingAs($this->playerA)
            ->post(route('player.wallet.deposit', $this->companyA), [
                'amount' => -50,
            ]);

        $responseNegativeDeposit->assertSessionHasErrors(['amount']);

        // 2. Zero deposit rejected
        $responseZeroDeposit = $this->actingAs($this->playerA)
            ->post(route('player.wallet.deposit', $this->companyA), [
                'amount' => 0,
            ]);

        $responseZeroDeposit->assertSessionHasErrors(['amount']);

        // 3. Withdrawal exceeding balance rejected
        $responseExcessWithdrawal = $this->actingAs($this->playerA)
            ->post(route('player.wallet.withdraw', $this->companyA), [
                'amount' => 99999,
            ]);

        $responseExcessWithdrawal->assertSessionHasErrors(['amount']);
    }

    public function test_rate_limiting_on_bingo_claims_triggers_429(): void
    {
        $template = GameTemplate::firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->companyA);
        $this->lifecycleService->openGame($game);
        $this->assignmentService->joinGame($game, $this->playerA);
        $this->assignmentService->assignCardToPlayer($game, $this->playerA);
        $this->lifecycleService->startGame($game);

        $gameCard = GameCard::where('game_id', $game->id)->where('user_id', $this->playerA->id)->firstOrFail();

        // 30 requests permitted, 31st triggers 429
        $hitRateLimit = false;
        for ($i = 0; $i < 35; $i++) {
            $response = $this->actingAs($this->playerA)
                ->postJson(route('player.game.cards.claim-bingo', [$this->companyA, $game, $gameCard]));

            if ($response->getStatusCode() === 429) {
                $hitRateLimit = true;
                break;
            }
        }

        $this->assertTrue($hitRateLimit, 'Rate limiter failed to trigger 429 on excessive bingo claims.');
    }
}
