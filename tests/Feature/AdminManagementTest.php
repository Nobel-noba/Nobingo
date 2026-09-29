<?php

namespace Tests\Feature;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Auth\Models\Role;
use App\Domains\Financial\Models\Transaction;
use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameCall;
use App\Domains\Games\Models\GameTemplate;
use App\Domains\Games\Services\GameLifecycleService;
use App\Domains\Patterns\Models\WinningPattern;
use App\Domains\Tenancy\Models\Company;
use App\Models\User;
use Database\Seeders\GameTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WinningPatternSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;

    protected Company $companyB;

    protected User $adminA;

    protected User $adminB;

    protected User $playerA;

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
            'balance' => 5000,
        ]);
        $this->playerA->assignRole(Role::PLAYER);
    }

    public function test_company_admin_can_view_player_directory_and_filter_by_search_or_status(): void
    {
        $playerTwo = User::factory()->create([
            'name' => 'UniqueZelda Walker',
            'email' => 'zelda.walker@unique-bingo.test',
            'company_id' => $this->companyA->id,
            'status' => 'suspended',
            'balance' => 1000,
        ]);
        $playerTwo->assignRole(Role::PLAYER);

        $playerOtherCompany = User::factory()->create([
            'name' => 'Bob Foreign',
            'company_id' => $this->companyB->id,
            'status' => 'active',
        ]);
        $playerOtherCompany->assignRole(Role::PLAYER);

        // 1. Unfiltered Index
        $response = $this->actingAs($this->adminA)
            ->get(route('company.admin.players.index', $this->companyA));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Company/Players/Index')
            ->has('players.data')
            ->where('company.slug', $this->companyA->slug)
        );

        // 2. Filter by search
        $searchResponse = $this->actingAs($this->adminA)
            ->get(route('company.admin.players.index', [$this->companyA, 'search' => 'UniqueZelda']));

        $searchResponse->assertOk();
        $searchResponse->assertInertia(fn (Assert $page) => $page
            ->component('Company/Players/Index')
            ->has('players.data', 1)
            ->where('players.data.0.email', 'zelda.walker@unique-bingo.test')
        );

        // 3. Filter by status
        $statusResponse = $this->actingAs($this->adminA)
            ->get(route('company.admin.players.index', [$this->companyA, 'status' => 'suspended']));

        $statusResponse->assertOk();
        $statusResponse->assertInertia(fn (Assert $page) => $page
            ->component('Company/Players/Index')
            ->has('players.data', 1)
            ->where('players.data.0.status', 'suspended')
        );
    }

    public function test_company_admin_can_view_player_dossier(): void
    {
        $response = $this->actingAs($this->adminA)
            ->get(route('company.admin.players.show', [$this->companyA, $this->playerA]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Company/Players/Show')
            ->where('player.id', $this->playerA->id)
            ->where('player.email', $this->playerA->email)
            ->has('recent_games')
            ->has('wins')
            ->has('transactions')
        );
    }

    public function test_company_admin_cannot_view_player_of_another_company(): void
    {
        $playerB = User::factory()->create([
            'company_id' => $this->companyB->id,
            'status' => 'active',
        ]);
        $playerB->assignRole(Role::PLAYER);

        $response = $this->actingAs($this->adminA)
            ->get(route('company.admin.players.show', [$this->companyA, $playerB]));

        $response->assertNotFound();
    }

    public function test_company_admin_can_toggle_player_status_with_audit_log(): void
    {
        $this->assertSame('active', $this->playerA->status);

        // Suspend player
        $response = $this->actingAs($this->adminA)
            ->patch(route('company.admin.players.toggle-status', [$this->companyA, $this->playerA]));

        $response->assertRedirect();
        $this->playerA->refresh();
        $this->assertSame('suspended', $this->playerA->status);
        $this->assertFalse($this->playerA->isActive());

        // Verify Audit Log
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->companyA->id,
            'user_id' => $this->adminA->id,
            'action' => AuditLog::ACTION_PLAYER_SUSPENDED,
            'auditable_type' => User::class,
            'auditable_id' => $this->playerA->id,
        ]);

        // Re-activate player
        $response2 = $this->actingAs($this->adminA)
            ->patch(route('company.admin.players.toggle-status', [$this->companyA, $this->playerA]));

        $response2->assertRedirect();
        $this->playerA->refresh();
        $this->assertSame('active', $this->playerA->status);
        $this->assertTrue($this->playerA->isActive());

        // Verify Audit Log
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->companyA->id,
            'user_id' => $this->adminA->id,
            'action' => AuditLog::ACTION_PLAYER_ACTIVATED,
            'auditable_type' => User::class,
            'auditable_id' => $this->playerA->id,
        ]);
    }

    public function test_company_admin_can_adjust_player_balance_credit_and_debit_with_audit_trail(): void
    {
        // Initial balance: 5000 ($50.00)
        // 1. Credit $25.00
        $responseCredit = $this->actingAs($this->adminA)
            ->post(route('company.admin.players.adjust-balance', [$this->companyA, $this->playerA]), [
                'is_credit' => true,
                'amount' => 25,
                'reason' => 'Customer support refund compensation',
            ]);

        $responseCredit->assertRedirect();
        $this->playerA->refresh();
        $this->assertSame(7500, $this->playerA->balance);

        // Verify Transaction Ledger
        $this->assertDatabaseHas('transactions', [
            'company_id' => $this->companyA->id,
            'user_id' => $this->playerA->id,
            'type' => Transaction::TYPE_ADJUSTMENT,
            'amount' => 2500,
            'balance_before' => 5000,
            'balance_after' => 7500,
            'status' => Transaction::STATUS_COMPLETED,
        ]);

        // Verify Audit Log
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->companyA->id,
            'user_id' => $this->adminA->id,
            'action' => AuditLog::ACTION_BALANCE_ADJUSTED,
            'auditable_type' => Transaction::class,
        ]);

        // 2. Debit $15.00
        $responseDebit = $this->actingAs($this->adminA)
            ->post(route('company.admin.players.adjust-balance', [$this->companyA, $this->playerA]), [
                'is_credit' => false,
                'amount' => 15,
                'reason' => 'Duplicate deposit correction',
            ]);

        $responseDebit->assertRedirect();
        $this->playerA->refresh();
        $this->assertSame(6000, $this->playerA->balance);

        // 3. Debit exceeding balance fails safely
        $responseExcess = $this->actingAs($this->adminA)
            ->post(route('company.admin.players.adjust-balance', [$this->companyA, $this->playerA]), [
                'is_credit' => false,
                'amount' => 999,
                'reason' => 'Too high',
            ]);

        $responseExcess->assertRedirect();
        $responseExcess->assertSessionHas('error');
        $this->assertSame(6000, $this->playerA->fresh()->balance);
    }

    public function test_company_admin_can_view_and_create_game_templates(): void
    {
        $pattern = WinningPattern::firstOrFail();

        // 1. Index
        $response = $this->actingAs($this->adminA)
            ->get(route('company.admin.templates.index', $this->companyA));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Company/Templates/Index')
            ->has('templates')
            ->has('patterns')
        );

        // 2. Store new template
        $responseStore = $this->actingAs($this->adminA)
            ->post(route('company.admin.templates.store', $this->companyA), [
                'name' => 'Midnight High Roller',
                'description' => 'Fast-paced progressive prize game',
                'pattern_mode' => 'single_pattern',
                'required_pattern_count' => 1,
                'allowed_pattern_ids' => [$pattern->id],
                'winner_policy' => 'first_valid',
                'default_call_interval' => 3,
                'default_min_players' => 2,
                'default_max_players' => 50,
                'default_entry_fee' => 5, // $5.00 -> 500 cents
                'fixed_prize' => 150,     // $150.00 -> 15000 cents
            ]);

        $responseStore->assertRedirect();

        $template = GameTemplate::where('name', 'Midnight High Roller')->firstOrFail();
        $this->assertSame($this->companyA->id, $template->company_id);
        $this->assertSame(500, $template->default_entry_fee);
        $this->assertSame(15000, $template->default_prize_configuration['fixed_prize']);
        $this->assertTrue($template->is_active);

        // Verify Audit Log
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->companyA->id,
            'user_id' => $this->adminA->id,
            'action' => AuditLog::ACTION_TEMPLATE_CREATED,
            'auditable_type' => GameTemplate::class,
            'auditable_id' => $template->id,
        ]);

        // 3. Toggle template status
        $responseToggle = $this->actingAs($this->adminA)
            ->patch(route('company.admin.templates.toggle', [$this->companyA, $template]));

        $responseToggle->assertRedirect();
        $this->assertFalse($template->fresh()->is_active);
    }

    public function test_company_admin_cannot_toggle_template_of_another_company(): void
    {
        $pattern = WinningPattern::firstOrFail();
        $templateB = GameTemplate::create([
            'company_id' => $this->companyB->id,
            'name' => 'Lucky Exclusive',
            'slug' => 'lucky-exclusive-123',
            'pattern_mode' => 'single_pattern',
            'required_pattern_count' => 1,
            'allowed_pattern_ids' => [$pattern->id],
            'winner_policy' => 'first_valid',
            'default_call_interval' => 5,
            'default_min_players' => 2,
            'default_max_players' => 100,
            'default_entry_fee' => 100,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminA)
            ->patch(route('company.admin.templates.toggle', [$this->companyA, $templateB]));

        $response->assertForbidden();
    }

    public function test_company_admin_can_view_audit_logs_with_filtering(): void
    {
        // Generate test audit logs
        AuditLog::create([
            'company_id' => $this->companyA->id,
            'user_id' => $this->adminA->id,
            'action' => AuditLog::ACTION_PLAYER_SUSPENDED,
            'description' => 'Suspended rogue player',
            'details' => ['player_id' => 999],
        ]);

        AuditLog::create([
            'company_id' => $this->companyA->id,
            'user_id' => $this->adminA->id,
            'action' => AuditLog::ACTION_BALANCE_ADJUSTED,
            'description' => 'Adjusted player balance',
            'details' => ['amount' => 500],
        ]);

        AuditLog::create([
            'company_id' => $this->companyB->id,
            'user_id' => $this->adminB->id,
            'action' => AuditLog::ACTION_PLAYER_SUSPENDED,
            'description' => 'Company B audit log',
        ]);

        // 1. Unfiltered logs: only Company A logs shown
        $response = $this->actingAs($this->adminA)
            ->get(route('company.admin.audit-logs.index', $this->companyA));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Company/AuditLogs/Index')
            ->has('logs.data', 2)
        );

        // 2. Filter by action
        $filteredResponse = $this->actingAs($this->adminA)
            ->get(route('company.admin.audit-logs.index', [$this->companyA, 'action' => AuditLog::ACTION_BALANCE_ADJUSTED]));

        $filteredResponse->assertOk();
        $filteredResponse->assertInertia(fn (Assert $page) => $page
            ->component('Company/AuditLogs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.action', AuditLog::ACTION_BALANCE_ADJUSTED)
        );
    }

    public function test_company_admin_can_view_executive_reports(): void
    {
        // Create transactions and game data
        Transaction::create([
            'company_id' => $this->companyA->id,
            'user_id' => $this->playerA->id,
            'type' => Transaction::TYPE_ENTRY_FEE,
            'amount' => 2000,
            'balance_before' => 5000,
            'balance_after' => 3000,
            'status' => Transaction::STATUS_COMPLETED,
        ]);

        Transaction::create([
            'company_id' => $this->companyA->id,
            'user_id' => $this->playerA->id,
            'type' => Transaction::TYPE_PRIZE,
            'amount' => 1500,
            'balance_before' => 3000,
            'balance_after' => 4500,
            'status' => Transaction::STATUS_COMPLETED,
        ]);

        $response = $this->actingAs($this->adminA)
            ->get(route('company.admin.reports.index', $this->companyA));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Company/Reports/Index')
            ->has('report.games')
            ->has('report.financial')
            ->has('report.players')
            ->where('report.financial.entry_fees', 2000)
            ->where('report.financial.prizes_paid', 1500)
            ->where('report.financial.net_house_earnings', 500)
        );
    }

    public function test_company_admin_can_inspect_game_audit_and_replay(): void
    {
        $template = GameTemplate::where('company_id', $this->companyA->id)->first() ?? GameTemplate::firstOrFail();

        $game = app(GameLifecycleService::class)->createFromTemplate($template, $this->companyA);
        $game->update([
            'status' => Game::STATUS_COMPLETED,
            'total_calls' => 2,
            'completed_at' => now(),
        ]);

        GameCall::create([
            'game_id' => $game->id,
            'ball_number' => 7,
            'letter' => 'B',
            'sequence_index' => 1,
            'called_at' => now()->subSeconds(10),
        ]);

        GameCall::create([
            'game_id' => $game->id,
            'ball_number' => 22,
            'letter' => 'I',
            'sequence_index' => 2,
            'called_at' => now()->subSeconds(5),
        ]);

        $response = $this->actingAs($this->adminA)
            ->get(route('company.admin.games.audit', [$this->companyA, $game]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Company/Games/Audit')
            ->where('game.id', $game->id)
            ->where('game.game_number', $game->game_number)
            ->has('calls', 2)
            ->where('calls.0.ball_number', 7)
            ->where('calls.1.ball_number', 22)
            ->has('players')
            ->has('winners')
            ->has('audit_logs')
        );
    }

    public function test_company_admin_cannot_inspect_game_audit_of_another_company(): void
    {
        $template = GameTemplate::firstOrFail();

        $foreignGame = app(GameLifecycleService::class)->createFromTemplate($template, $this->companyB);
        $foreignGame->update([
            'status' => Game::STATUS_COMPLETED,
            'total_calls' => 0,
        ]);

        $response = $this->actingAs($this->adminA)
            ->get(route('company.admin.games.audit', [$this->companyA, $foreignGame]));

        $response->assertNotFound();
    }

    public function test_regular_player_is_denied_from_admin_modules(): void
    {
        $response = $this->actingAs($this->playerA)
            ->get(route('company.admin.players.index', $this->companyA));

        $response->assertForbidden();

        $responseReports = $this->actingAs($this->playerA)
            ->get(route('company.admin.reports.index', $this->companyA));

        $responseReports->assertForbidden();

        $responseAudit = $this->actingAs($this->playerA)
            ->get(route('company.admin.audit-logs.index', $this->companyA));

        $responseAudit->assertForbidden();
    }
}
