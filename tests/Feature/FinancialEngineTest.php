<?php

namespace Tests\Feature;

use App\Domains\Auth\Models\Role;
use App\Domains\Cards\Services\BingoCardGenerator;
use App\Domains\Cards\Services\BingoCardValidator;
use App\Domains\Financial\Contracts\PaymentGatewayInterface;
use App\Domains\Financial\Models\Transaction;
use App\Domains\Financial\Services\LedgerService;
use App\Domains\Financial\Services\PrizeCalculationService;
use App\Domains\Financial\Services\PrizeDistributionService;
use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameCall;
use App\Domains\Games\Models\GameCard;
use App\Domains\Games\Models\GameTemplate;
use App\Domains\Games\Services\CardAssignmentService;
use App\Domains\Games\Services\GameLifecycleService;
use App\Domains\Tenancy\Models\Company;
use App\Domains\Winners\Models\GameWinner;
use App\Domains\Winners\Services\BingoVerificationService;
use App\Models\User;
use Database\Seeders\GameTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WinningPatternSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialEngineTest extends TestCase
{
    use RefreshDatabase;

    protected LedgerService $ledgerService;

    protected PaymentGatewayInterface $paymentGateway;

    protected PrizeCalculationService $calculationService;

    protected PrizeDistributionService $distributionService;

    protected GameLifecycleService $lifecycleService;

    protected CardAssignmentService $assignmentService;

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

        $this->ledgerService = app(LedgerService::class);
        $this->paymentGateway = app(PaymentGatewayInterface::class);
        $this->calculationService = app(PrizeCalculationService::class);
        $this->distributionService = app(PrizeDistributionService::class);
        $this->lifecycleService = app(GameLifecycleService::class);
        $this->assignmentService = app(CardAssignmentService::class);
        $this->verificationService = app(BingoVerificationService::class);
        $this->cardGenerator = new BingoCardGenerator(new BingoCardValidator);

        $this->company = Company::where('slug', 'acme-bingo')->firstOrFail();
    }

    public function test_user_wallet_deposit_records_transaction_and_increments_balance(): void
    {
        $player = User::factory()->create([
            'company_id' => $this->company->id,
            'balance' => 0,
        ]);

        $tx = $this->paymentGateway->deposit($player, 5000); // $50.00

        $this->assertSame(5000, $player->fresh()->balance);
        $this->assertSame(Transaction::TYPE_DEPOSIT, $tx->type);
        $this->assertSame(5000, $tx->amount);
        $this->assertSame(0, $tx->balance_before);
        $this->assertSame(5000, $tx->balance_after);

        $this->assertDatabaseHas('transactions', [
            'id' => $tx->id,
            'company_id' => $this->company->id,
            'user_id' => $player->id,
            'type' => Transaction::TYPE_DEPOSIT,
            'amount' => 5000,
            'balance_before' => 0,
            'balance_after' => 5000,
            'status' => Transaction::STATUS_COMPLETED,
        ]);
    }

    public function test_user_wallet_withdrawal_records_transaction_and_decrements_balance(): void
    {
        $player = User::factory()->create([
            'company_id' => $this->company->id,
            'balance' => 10000, // $100.00
        ]);

        $tx = $this->paymentGateway->withdraw($player, 4000); // $40.00

        $this->assertSame(6000, $player->fresh()->balance);
        $this->assertSame(Transaction::TYPE_WITHDRAWAL, $tx->type);
        $this->assertSame(4000, $tx->amount);
        $this->assertSame(10000, $tx->balance_before);
        $this->assertSame(6000, $tx->balance_after);

        $this->assertDatabaseHas('transactions', [
            'id' => $tx->id,
            'company_id' => $this->company->id,
            'user_id' => $player->id,
            'type' => Transaction::TYPE_WITHDRAWAL,
            'amount' => 4000,
            'balance_before' => 10000,
            'balance_after' => 6000,
            'status' => Transaction::STATUS_COMPLETED,
        ]);
    }

    public function test_withdrawal_with_insufficient_balance_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient wallet balance');

        $player = User::factory()->create([
            'company_id' => $this->company->id,
            'balance' => 1000, // $10.00
        ]);

        $this->paymentGateway->withdraw($player, 2500); // $25.00
    }

    public function test_joining_game_deducts_entry_fee_via_ledger(): void
    {
        $this->cardGenerator->generateBatch($this->company, 5);
        $player = User::factory()->create([
            'company_id' => $this->company->id,
            'balance' => 3000, // $30.00
        ]);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company, [
            'entry_fee' => 750, // $7.50
        ]);
        $this->lifecycleService->openGame($game);

        $this->assignmentService->joinGame($game, $player);

        $this->assertSame(2250, $player->fresh()->balance);
        $this->assertDatabaseHas('transactions', [
            'company_id' => $this->company->id,
            'user_id' => $player->id,
            'type' => Transaction::TYPE_ENTRY_FEE,
            'amount' => 750,
            'balance_before' => 3000,
            'balance_after' => 2250,
            'reference_type' => Game::class,
            'reference_id' => $game->id,
        ]);
    }

    public function test_entry_fee_idempotency_prevents_duplicate_charge(): void
    {
        $player = User::factory()->create([
            'company_id' => $this->company->id,
            'balance' => 5000,
        ]);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company, [
            'entry_fee' => 1000,
        ]);

        $tx1 = $this->ledgerService->recordEntryFee($player, $game);
        $this->assertSame(4000, $player->fresh()->balance);

        // Second call with same user and game returns existing transaction without double deducting
        $tx2 = $this->ledgerService->recordEntryFee($player, $game);
        $this->assertSame($tx1->id, $tx2->id);
        $this->assertSame(4000, $player->fresh()->balance);
        $this->assertSame(1, Transaction::where('user_id', $player->id)->where('type', Transaction::TYPE_ENTRY_FEE)->count());
    }

    public function test_winning_claim_distributes_prize_via_ledger_and_updates_payout_status(): void
    {
        $this->cardGenerator->generateBatch($this->company, 5);
        $player = User::factory()->create([
            'company_id' => $this->company->id,
            'balance' => 2000, // $20.00
        ]);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company, [
            'entry_fee' => 500,
            'prize_configuration' => ['fixed_prize' => 10000], // $100.00 fixed
        ]);
        $this->lifecycleService->openGame($game);

        $this->assignmentService->joinGame($game, $player);
        $gameCard = GameCard::where('game_id', $game->id)->where('user_id', $player->id)->firstOrFail();
        $this->lifecycleService->startGame($game);

        // Player's balance after entry fee: $20 - $5 = $15 (1500 cents)
        $this->assertSame(1500, $player->fresh()->balance);

        // Call numbers to complete horizontal line 0
        $row = $gameCard->version->grid[0];
        foreach ($row as $idx => $num) {
            GameCall::create([
                'game_id' => $game->id,
                'sequence_index' => $idx + 1,
                'ball_number' => $num,
                'letter' => GameCall::getLetterForNumber($num),
                'called_at' => now(),
            ]);
        }

        $result = $this->verificationService->claimBingo($game, $gameCard, $player);
        $this->assertTrue($result['success']);

        /** @var GameWinner $winner */
        $winner = $result['winner'];
        $this->assertSame(GameWinner::PAYOUT_STATUS_PAID, $winner->fresh()->payout_status);
        $this->assertSame(10000, $winner->payout_amount);

        // Player received $100.00 prize: 1500 + 10000 = 11500 cents ($115.00)
        $this->assertSame(11500, $player->fresh()->balance);

        $this->assertDatabaseHas('transactions', [
            'company_id' => $this->company->id,
            'user_id' => $player->id,
            'type' => Transaction::TYPE_PRIZE,
            'amount' => 10000,
            'balance_before' => 1500,
            'balance_after' => 11500,
            'reference_type' => GameWinner::class,
            'reference_id' => $winner->id,
            'status' => Transaction::STATUS_COMPLETED,
        ]);
    }

    public function test_prize_distribution_idempotency_prevents_duplicate_payout(): void
    {
        $player = User::factory()->create([
            'company_id' => $this->company->id,
            'balance' => 1000,
        ]);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company, [
            'entry_fee' => 0,
        ]);

        $this->cardGenerator->generateBatch($this->company, 5);
        $this->lifecycleService->openGame($game);
        $this->assignmentService->joinGame($game, $player);
        $gameCard = GameCard::where('game_id', $game->id)->where('user_id', $player->id)->firstOrFail();

        $winner = GameWinner::create([
            'company_id' => $this->company->id,
            'game_id' => $game->id,
            'game_card_id' => $gameCard->id,
            'user_id' => $player->id,
            'winning_patterns_snapshot' => [],
            'winning_call_sequence' => 10,
            'winning_ball_number' => 25,
            'claim_type' => 'manual',
            'payout_amount' => 5000,
            'split_ratio' => 1.0,
            'payout_status' => GameWinner::PAYOUT_STATUS_PENDING,
        ]);

        $tx1 = $this->ledgerService->recordPrizePayout($winner);
        $this->assertSame(6000, $player->fresh()->balance);

        // Second call with same winner
        $tx2 = $this->ledgerService->recordPrizePayout($winner);
        $this->assertSame($tx1->id, $tx2->id);
        $this->assertSame(6000, $player->fresh()->balance);
        $this->assertSame(1, Transaction::where('user_id', $player->id)->where('type', Transaction::TYPE_PRIZE)->count());
    }

    public function test_simultaneous_winners_split_prize_pool_correctly_and_reconcile_ledger(): void
    {
        $this->cardGenerator->generateBatch($this->company, 5);
        $player1 = User::factory()->create(['company_id' => $this->company->id, 'balance' => 5000]);
        $player2 = User::factory()->create(['company_id' => $this->company->id, 'balance' => 5000]);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company, [
            'entry_fee' => 0,
            'winner_policy' => Game::WINNER_POLICY_SIMULTANEOUS,
            'prize_configuration' => ['fixed_prize' => 10000], // $100.00
        ]);
        $this->lifecycleService->openGame($game);

        $this->assignmentService->joinGame($game, $player1);
        $this->assignmentService->joinGame($game, $player2);

        $card1 = GameCard::where('game_id', $game->id)->where('user_id', $player1->id)->firstOrFail();
        $card2 = GameCard::where('game_id', $game->id)->where('user_id', $player2->id)->firstOrFail();

        $this->lifecycleService->startGame($game);

        // Call numbers to complete row 0 for both cards
        $numbersToCall = array_unique(array_merge($card1->version->grid[0], $card2->version->grid[0]));
        $seq = 1;
        foreach ($numbersToCall as $num) {
            GameCall::create([
                'game_id' => $game->id,
                'sequence_index' => $seq++,
                'ball_number' => $num,
                'letter' => GameCall::getLetterForNumber($num),
                'called_at' => now(),
            ]);
        }

        // Player 1 claims
        $claim1 = $this->verificationService->claimBingo($game, $card1, $player1);
        $this->assertTrue($claim1['success']);

        // Player 2 claims on the exact same latest ball
        $claim2 = $this->verificationService->claimBingo($game, $card2, $player2);
        $this->assertTrue($claim2['success']);

        // 2 co-winners split $100.00 equally -> $50.00 (5000 cents) each
        // Both players started with 5000 and should now have 5000 + 5000 = 10000 cents
        $this->assertSame(10000, $player1->fresh()->balance);
        $this->assertSame(10000, $player2->fresh()->balance);

        $winners = GameWinner::where('game_id', $game->id)->get();
        $this->assertCount(2, $winners);
        $this->assertSame(5000, $winners[0]->payout_amount);
        $this->assertSame(5000, $winners[1]->payout_amount);
    }

    public function test_cancelling_game_refunds_entry_fees_to_all_registered_players(): void
    {
        $this->cardGenerator->generateBatch($this->company, 5);
        $player1 = User::factory()->create(['company_id' => $this->company->id, 'balance' => 2000]);
        $player2 = User::factory()->create(['company_id' => $this->company->id, 'balance' => 3000]);

        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company, [
            'entry_fee' => 500,
        ]);
        $this->lifecycleService->openGame($game);

        $this->assignmentService->joinGame($game, $player1);
        $this->assignmentService->joinGame($game, $player2);

        // Balances deducted: player1 = 1500, player2 = 2500
        $this->assertSame(1500, $player1->fresh()->balance);
        $this->assertSame(2500, $player2->fresh()->balance);

        // Cancel game
        $this->lifecycleService->cancelGame($game);

        $this->assertSame(Game::STATUS_CANCELLED, $game->fresh()->status);

        // Both players refunded $5.00
        $this->assertSame(2000, $player1->fresh()->balance);
        $this->assertSame(3000, $player2->fresh()->balance);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $player1->id,
            'type' => Transaction::TYPE_REFUND,
            'amount' => 500,
            'balance_before' => 1500,
            'balance_after' => 2000,
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $player2->id,
            'type' => Transaction::TYPE_REFUND,
            'amount' => 500,
            'balance_before' => 2500,
            'balance_after' => 3000,
        ]);
    }

    public function test_admin_adjustment_credits_and_debits_balance_correctly(): void
    {
        $player = User::factory()->create([
            'company_id' => $this->company->id,
            'balance' => 1000,
        ]);

        // Credit adjustment of $15.00 (1500 cents)
        $tx1 = $this->ledgerService->recordAdjustment($player, 1500, true, 'Loyalty bonus');
        $this->assertSame(2500, $player->fresh()->balance);
        $this->assertSame(Transaction::TYPE_ADJUSTMENT, $tx1->type);
        $this->assertSame(1000, $tx1->balance_before);
        $this->assertSame(2500, $tx1->balance_after);

        // Debit adjustment of $5.00 (500 cents)
        $tx2 = $this->ledgerService->recordAdjustment($player, 500, false, 'Administrative correction');
        $this->assertSame(2000, $player->fresh()->balance);
        $this->assertSame(2500, $tx2->balance_before);
        $this->assertSame(2000, $tx2->balance_after);
    }

    public function test_company_admin_can_view_tenant_ledger_with_statistics(): void
    {
        $admin = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $admin->assignRole(Role::COMPANY_ADMIN);

        // Create some sample transactions
        $player = User::factory()->create(['company_id' => $this->company->id, 'balance' => 0]);
        $this->paymentGateway->deposit($player, 5000);

        $response = $this->actingAs($admin)->get("/c/{$this->company->slug}/admin/ledger");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Company/Ledger/Index')
            ->has('transactions.data', 1)
            ->where('statistics.deposits', 5000)
            ->has('filters')
        );
    }

    public function test_player_can_view_wallet_and_deposit_withdraw_via_endpoints(): void
    {
        $player = User::factory()->create([
            'company_id' => $this->company->id,
            'balance' => 5000, // $50.00
        ]);
        $player->assignRole(Role::PLAYER);

        // View wallet
        $viewResponse = $this->actingAs($player)->get("/c/{$this->company->slug}/wallet");
        $viewResponse->assertOk();
        $viewResponse->assertInertia(fn ($page) => $page
            ->component('Player/Wallet')
            ->where('balance', 5000)
            ->has('transactions')
        );

        // Direct self-deposit is disabled for players
        $depositResponse = $this->actingAs($player)->post("/c/{$this->company->slug}/wallet/deposit", [
            'amount' => 25,
        ]);
        $depositResponse->assertSessionHasErrors(['deposit']);
        $this->assertSame(5000, $player->fresh()->balance);

        // Company admin deposits funds to player account ($25)
        $admin = User::factory()->create(['company_id' => $this->company->id, 'status' => 'active']);
        $admin->assignRole(Role::COMPANY_ADMIN);
        $manualResponse = $this->actingAs($admin)->post("/c/{$this->company->slug}/admin/players/manual-deposit", [
            'user_id' => $player->id,
            'amount' => 25,
        ]);
        $manualResponse->assertSessionHas('success');
        $this->assertSame(7500, $player->fresh()->balance);

        // Withdraw $15
        $withdrawResponse = $this->actingAs($player)->post("/c/{$this->company->slug}/wallet/withdraw", [
            'amount' => 15,
        ]);
        $withdrawResponse->assertRedirect();
        $this->assertSame(6000, $player->fresh()->balance);
    }

    public function test_strict_tenant_isolation_on_ledger(): void
    {
        $companyB = Company::where('slug', 'lucky-star')->first() ?? Company::create([
            'name' => 'Lucky Star Bingo',
            'slug' => 'lucky-star',
            'status' => 'active',
        ]);

        $adminA = User::factory()->create(['company_id' => $this->company->id]);
        $adminA->assignRole(Role::COMPANY_ADMIN);

        $playerA = User::factory()->create(['company_id' => $this->company->id, 'balance' => 0]);
        $playerB = User::factory()->create(['company_id' => $companyB->id, 'balance' => 0]);

        $this->paymentGateway->deposit($playerA, 3000);
        $this->paymentGateway->deposit($playerB, 7000);

        // Admin A checks ledger for Acme Bingo: must ONLY see Player A's transaction ($30.00), not Player B's ($70.00)
        $response = $this->actingAs($adminA)->get("/c/{$this->company->slug}/admin/ledger");
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Company/Ledger/Index')
            ->has('transactions.data', 1)
            ->where('transactions.data.0.amount', 3000)
            ->where('statistics.deposits', 3000)
        );
    }
}
