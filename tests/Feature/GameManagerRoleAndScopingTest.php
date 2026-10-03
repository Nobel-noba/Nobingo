<?php

namespace Tests\Feature;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Auth\Models\Role;
use App\Domains\Cards\Services\BingoCardGenerator;
use App\Domains\Cards\Services\BingoCardValidator;
use App\Domains\Financial\Models\Transaction;
use App\Domains\Financial\Services\LedgerService;
use App\Domains\Financial\Services\PrizeDistributionService;
use App\Domains\Games\Models\GameCard;
use App\Domains\Games\Models\GameTemplate;
use App\Domains\Games\Services\CardAssignmentService;
use App\Domains\Games\Services\GameLifecycleService;
use App\Domains\Reports\Services\ReportingService;
use App\Domains\Tenancy\Models\Company;
use App\Domains\Tenancy\Models\GameManagerPlayer;
use App\Domains\Winners\Models\GameWinner;
use App\Models\User;
use Database\Seeders\GameTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WinningPatternSeeder;
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
        $this->seed([
            RolePermissionSeeder::class,
            WinningPatternSeeder::class,
            GameTemplateSeeder::class,
        ]);

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

        // Transactions & Money Flows Monitor
        $response = $this->actingAs($this->gameManager)->get("/c/{$this->company->slug}/admin/transactions");
        $response->assertStatus(200);

        // Treasury & Ledger
        $response = $this->actingAs($this->gameManager)->get("/c/{$this->company->slug}/admin/ledger");
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

    public function test_game_manager_cannot_suspend_or_activate_players(): void
    {
        $player = User::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);
        $player->assignRole(Role::PLAYER);

        // Game Manager attempts to suspend the player -> 403 Forbidden
        $response = $this->actingAs($this->gameManager)
            ->patch("/c/{$this->company->slug}/admin/players/{$player->id}/toggle-status");
        $response->assertStatus(403);
        $this->assertSame('active', $player->fresh()->status);

        // Company Admin suspends the player -> 302 Redirect & status updated
        $adminResponse = $this->actingAs($this->companyAdmin)
            ->patch("/c/{$this->company->slug}/admin/players/{$player->id}/toggle-status");
        $adminResponse->assertStatus(302);
        $this->assertSame('suspended', $player->fresh()->status);
    }

    public function test_game_manager_can_only_manage_their_own_games(): void
    {
        $manager2 = User::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);
        $manager2->assignRole(Role::GAME_MANAGER);

        $template = GameTemplate::firstOrFail();
        $lifecycleService = new GameLifecycleService;

        $game1 = $lifecycleService->createFromTemplate($template, $this->company, ['name' => 'GM1 Room'], $this->gameManager->id);
        $game2 = $lifecycleService->createFromTemplate($template, $this->company, ['name' => 'GM2 Room'], $manager2->id);

        // 1. GM 1 only sees their own game in the index
        $response = $this->actingAs($this->gameManager)->get("/c/{$this->company->slug}/admin/games");
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Company/Games/Index')
            ->has('games.data', 1)
            ->where('games.data.0.id', $game1->id)
        );

        // 2. GM 1 cannot view GM 2's game
        $this->actingAs($this->gameManager)
            ->get("/c/{$this->company->slug}/admin/games/{$game2->id}")
            ->assertStatus(403);

        // 3. GM 1 cannot call balls on GM 2's game
        $this->actingAs($this->gameManager)
            ->post("/c/{$this->company->slug}/admin/games/{$game2->id}/call-next")
            ->assertStatus(403);

        // 4. GM 1 cannot assign cards on GM 2's game
        $this->actingAs($this->gameManager)
            ->post("/c/{$this->company->slug}/admin/games/{$game2->id}/assign-card", ['card_number' => 1])
            ->assertStatus(403);

        // 5. GM 1 cannot view GM 2's game audit
        $this->actingAs($this->gameManager)
            ->get("/c/{$this->company->slug}/admin/games/{$game2->id}/audit")
            ->assertStatus(403);

        // 6. GM 1 CAN access and audit their own game
        $this->actingAs($this->gameManager)
            ->get("/c/{$this->company->slug}/admin/games/{$game1->id}")
            ->assertStatus(200);
        $this->actingAs($this->gameManager)
            ->get("/c/{$this->company->slug}/admin/games/{$game1->id}/audit")
            ->assertStatus(200);

        // 7. Company Admin can access any game and audit
        $this->actingAs($this->companyAdmin)
            ->get("/c/{$this->company->slug}/admin/games/{$game2->id}")
            ->assertStatus(200);
        $this->actingAs($this->companyAdmin)
            ->get("/c/{$this->company->slug}/admin/games/{$game2->id}/audit")
            ->assertStatus(200);
    }

    public function test_company_admin_can_filter_audit_logs_and_games_by_game_manager(): void
    {
        $manager2 = User::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);
        $manager2->assignRole(Role::GAME_MANAGER);

        $template = GameTemplate::firstOrFail();
        $lifecycleService = new GameLifecycleService;

        $game1 = $lifecycleService->createFromTemplate($template, $this->company, ['name' => 'GM1 Room'], $this->gameManager->id);
        $game2 = $lifecycleService->createFromTemplate($template, $this->company, ['name' => 'GM2 Room'], $manager2->id);

        AuditLog::create([
            'company_id' => $this->company->id,
            'user_id' => $this->gameManager->id,
            'action' => 'GM1_LOG',
            'description' => 'Log for GM1',
        ]);

        AuditLog::create([
            'company_id' => $this->company->id,
            'user_id' => $manager2->id,
            'action' => 'GM2_LOG',
            'description' => 'Log for GM2',
        ]);

        // Admin filters games by GM1
        $response = $this->actingAs($this->companyAdmin)->get("/c/{$this->company->slug}/admin/games?manager_id={$this->gameManager->id}");
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Company/Games/Index')
            ->has('games.data', 1)
            ->where('games.data.0.id', $game1->id)
        );

        // Admin filters audit logs by GM1
        $logResponse = $this->actingAs($this->companyAdmin)->get("/c/{$this->company->slug}/admin/audit-logs?manager_id={$this->gameManager->id}");
        $logResponse->assertStatus(200);
        $logResponse->assertInertia(fn ($page) => $page
            ->component('Company/AuditLogs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.action', 'GM1_LOG')
        );
    }

    public function test_walkin_card_assignment_and_payout_creates_financial_transactions(): void
    {
        $generator = new BingoCardGenerator(new BingoCardValidator);
        $generator->generateBatch($this->company, 5);

        $template = GameTemplate::firstOrFail();
        $lifecycleService = new GameLifecycleService;
        $assignmentService = new CardAssignmentService;
        $winnerService = app(PrizeDistributionService::class);

        $game = $lifecycleService->createFromTemplate($template, $this->company, [
            'name' => 'Walkin Room',
            'entry_fee' => 750,
        ], $this->gameManager->id);

        $lifecycleService->openGame($game);

        // Assign walk-in cash card
        $assignmentService->assignWalkInCard($game, 1, 'Walkin Table 3');

        // Check entry fee transaction created with null user_id
        $entryTx = Transaction::where('company_id', $this->company->id)
            ->where('type', Transaction::TYPE_ENTRY_FEE)
            ->whereNull('user_id')
            ->where('reference_code', "WALKIN-G{$game->id}-C1")
            ->first();

        $this->assertNotNull($entryTx);
        $this->assertSame(750, $entryTx->amount);

        // Start game and distribute prize to walk-in winner
        $lifecycleService->startGame($game);
        $walkInCard = GameCard::where('game_id', $game->id)->whereNull('user_id')->firstOrFail();
        $winner = GameWinner::create([
            'company_id' => $game->company_id,
            'game_id' => $game->id,
            'game_card_id' => $walkInCard->id,
            'user_id' => null,
            'claim_type' => GameWinner::CLAIM_TYPE_MANUAL,
            'winning_patterns_snapshot' => ['single_line'],
            'winning_call_sequence' => 1,
            'winning_ball_number' => 15,
            'payout_amount' => 1500,
            'payout_status' => GameWinner::PAYOUT_STATUS_PENDING,
            'claimed_at' => now(),
        ]);
        $winnerService->distributeSingleWinner($winner);

        // Check prize transaction created with null user_id
        $prizeTx = Transaction::where('company_id', $this->company->id)
            ->where('type', Transaction::TYPE_PRIZE)
            ->whereNull('user_id')
            ->where('reference_code', "PRIZE-WALKIN-GW{$winner->id}")
            ->first();

        $this->assertNotNull($prizeTx);
        $this->assertSame(1500, $prizeTx->amount);

        // Verify Game Manager can view this in Transactions & Ledger monitor
        $response = $this->actingAs($this->gameManager)->get("/c/{$this->company->slug}/admin/transactions");
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Company/Ledger/Index')
            ->has('transactions.data', 2)
        );
    }

    public function test_multi_manager_player_roster_and_isolated_wallets(): void
    {
        $managerRole = Role::where('slug', Role::GAME_MANAGER)->firstOrFail();
        $playerRole = Role::where('slug', Role::PLAYER)->firstOrFail();

        // Create second Game Manager
        $manager2 = User::factory()->create(['company_id' => $this->company->id]);
        $manager2->roles()->attach($managerRole);

        // GM1 registers player
        $regResponse = $this->actingAs($this->gameManager)->post("/c/{$this->company->slug}/admin/players", [
            'name' => 'Charlie Player',
            'email' => 'charlie@acme.test',
            'password' => 'secret123',
            'initial_deposit' => 30, // $30.00
        ]);
        $regResponse->assertRedirect();

        $player = User::where('email', 'charlie@acme.test')->firstOrFail();
        $this->assertTrue($player->mustResetPassword());

        // Player is on GM1 roster with $30.00 balance
        $gm1Roster = GameManagerPlayer::where('game_manager_id', $this->gameManager->id)
            ->where('player_id', $player->id)
            ->first();
        $this->assertNotNull($gm1Roster);
        $this->assertSame(3000, $gm1Roster->balance);

        // Player is NOT yet on GM2 roster
        $gm2Roster = GameManagerPlayer::where('game_manager_id', $manager2->id)
            ->where('player_id', $player->id)
            ->first();
        $this->assertNull($gm2Roster);

        // GM2 authorizes existing player Charlie with initial deposit of $15.00
        $authResponse = $this->actingAs($manager2)->post("/c/{$this->company->slug}/admin/players/authorize-existing", [
            'player_id' => $player->id,
            'initial_deposit' => 15,
        ]);
        $authResponse->assertRedirect();

        $gm2Roster = GameManagerPlayer::where('game_manager_id', $manager2->id)
            ->where('player_id', $player->id)
            ->first();
        $this->assertNotNull($gm2Roster);
        $this->assertSame(1500, $gm2Roster->balance);
        $this->assertSame(3000, $gm1Roster->fresh()->balance);

        // GM2 cannot withdraw more than GM2's isolated balance
        $withdrawFailResponse = $this->actingAs($manager2)->post("/c/{$this->company->slug}/admin/players/{$player->id}/withdraw", [
            'amount' => 20, // requested $20, GM2 balance is only $15
        ]);
        $withdrawFailResponse->assertSessionHas('error');
        $this->assertSame(1500, $gm2Roster->fresh()->balance);

        // GM2 withdraws $10 successfully
        $withdrawSuccessResponse = $this->actingAs($manager2)->post("/c/{$this->company->slug}/admin/players/{$player->id}/withdraw", [
            'amount' => 10,
        ]);
        $withdrawSuccessResponse->assertSessionHas('success');
        $this->assertSame(500, $gm2Roster->fresh()->balance);
        $this->assertSame(3000, $gm1Roster->fresh()->balance); // GM1 balance untouched
    }

    public function test_observer_mode_allows_admin_view_but_blocks_game_mutation(): void
    {
        $generator = new BingoCardGenerator(new BingoCardValidator);
        $generator->generateBatch($this->company, 5);

        $template = GameTemplate::firstOrFail();
        $lifecycleService = new GameLifecycleService;

        // Game created by GM1
        $game = $lifecycleService->createFromTemplate($template, $this->company, [
            'name' => 'GM1 Hosted Game',
            'entry_fee' => 500,
        ], $this->gameManager->id);

        $lifecycleService->openGame($game);

        // Admin views the game -> Allowed in Observer Mode
        $viewResponse = $this->actingAs($this->companyAdmin)->get("/c/{$this->company->slug}/admin/games/{$game->id}");
        $viewResponse->assertStatus(200);
        $viewResponse->assertInertia(fn ($page) => $page
            ->component('Company/Games/Show')
            ->where('is_observer', true)
        );

        // Admin tries to mutate game status -> 403 Forbidden (Observer Mode)
        $statusResponse = $this->actingAs($this->companyAdmin)->patch("/c/{$this->company->slug}/admin/games/{$game->id}/status", [
            'status' => 'starting',
        ]);
        $statusResponse->assertStatus(403);

        // Admin tries to call ball -> 403 Forbidden
        $callResponse = $this->actingAs($this->companyAdmin)->post("/c/{$this->company->slug}/admin/games/{$game->id}/call-next");
        $callResponse->assertStatus(403);

        // Host Game Manager CAN mutate status
        $gmStatusResponse = $this->actingAs($this->gameManager)->patch("/c/{$this->company->slug}/admin/games/{$game->id}/status", [
            'status' => 'starting',
        ]);
        $gmStatusResponse->assertRedirect();
        $this->assertSame('starting', $game->fresh()->status);
    }

    public function test_walkin_cash_flow_and_manager_cash_on_hand_reporting(): void
    {
        $generator = new BingoCardGenerator(new BingoCardValidator);
        $generator->generateBatch($this->company, 5);

        $template = GameTemplate::firstOrFail();
        $lifecycleService = new GameLifecycleService;
        $assignmentService = new CardAssignmentService;

        // GM1 hosts a game with $10.00 entry fee
        $game = $lifecycleService->createFromTemplate($template, $this->company, [
            'name' => 'GM1 Cash Room',
            'entry_fee' => 1000,
        ], $this->gameManager->id);
        $lifecycleService->openGame($game);

        // Walk-in card purchase ($10.00 physical cash inflow)
        $assignmentService->assignWalkInCard($game, 1, 'Table 1');

        // Registered player counter deposit ($25.00 physical cash inflow)
        $player = User::factory()->create(['company_id' => $this->company->id]);
        $playerRole = Role::where('slug', Role::PLAYER)->firstOrFail();
        $player->roles()->attach($playerRole);

        app(LedgerService::class)->recordDeposit(
            $player,
            2500,
            referenceCode: 'TEST-DEP',
            managerId: $this->gameManager->id
        );

        // Counter withdrawal ($5.00 physical cash outflow)
        app(LedgerService::class)->recordWithdrawal(
            $player,
            500,
            referenceCode: 'TEST-WTH',
            managerId: $this->gameManager->id
        );

        // Inflow: $10.00 (walkin) + $25.00 (deposit) = $35.00 (3500 cents)
        // Outflow: $5.00 (withdrawal) = 500 cents
        // Net Cash on Hand: $30.00 (3000 cents)

        $reportingService = app(ReportingService::class);
        $report = $reportingService->getCompanyReport($this->company, $this->gameManager->id);

        $this->assertSame(1000, $report['walkin']['sales_amount']);
        $this->assertSame(3500, $report['cash_on_hand']['period_inflow']);
        $this->assertSame(500, $report['cash_on_hand']['period_outflow']);
        $this->assertSame(3000, $report['cash_on_hand']['period_net_flow']);
        $this->assertSame(3000, $report['cash_on_hand']['total_cash_on_hand']);

        // GM views reports page -> Scoped to GM
        $gmReportResponse = $this->actingAs($this->gameManager)->get("/c/{$this->company->slug}/admin/reports");
        $gmReportResponse->assertStatus(200);
        $gmReportResponse->assertInertia(fn ($page) => $page
            ->component('Company/Reports/Index')
            ->where('report.is_game_manager', true)
            ->where('report.cash_on_hand.total_cash_on_hand', 3000)
        );

        // Company Admin views reports page -> Sees manager breakdown
        $adminReportResponse = $this->actingAs($this->companyAdmin)->get("/c/{$this->company->slug}/admin/reports");
        $adminReportResponse->assertStatus(200);
        $adminReportResponse->assertInertia(fn ($page) => $page
            ->component('Company/Reports/Index')
            ->where('report.is_game_manager', false)
            ->has('report.managers_breakdown')
        );
    }
}
