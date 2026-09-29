<?php

namespace Tests\Feature;

use App\Domains\Auth\Models\Role;
use App\Domains\Cards\Models\BingoCard;
use App\Domains\Cards\Services\BingoCardGenerator;
use App\Domains\Cards\Services\BingoCardValidator;
use App\Domains\Financial\Models\DepositRequest;
use App\Domains\Financial\Models\PaymentAccount;
use App\Domains\Financial\Models\Transaction;
use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameCall;
use App\Domains\Games\Models\GameCard;
use App\Domains\Games\Models\GamePlayer;
use App\Domains\Games\Models\GameTemplate;
use App\Domains\Games\Services\CardAssignmentService;
use App\Domains\Games\Services\GameLifecycleService;
use App\Domains\Platform\Models\PlatformSetting;
use App\Domains\Tenancy\Models\Company;
use App\Domains\Winners\Models\GameWinner;
use App\Domains\Winners\Services\BingoVerificationService;
use App\Models\User;
use Database\Seeders\GameTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WinningPatternSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinancialRevenueAndWalkInTest extends TestCase
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

        Storage::fake('public');

        $this->assignmentService = new CardAssignmentService;
        $this->lifecycleService = new GameLifecycleService;

        // Create company with initial positive credit balance
        $this->company = Company::create([
            'name' => 'Royal Bingo Hall',
            'slug' => 'royal-bingo',
            'status' => 'active',
            'credit_balance' => 50000, // $500.00
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
            'balance' => 5000, // $50.00
        ]);
        $this->player->assignRole(Role::PLAYER);

        $this->platformOwner = User::factory()->create([
            'company_id' => null,
            'status' => 'active',
        ]);
        $this->platformOwner->assignRole(Role::PLATFORM_OWNER);

        // Inventory cards
        $generator = new BingoCardGenerator(new BingoCardValidator);
        $generator->generateBatch($this->company, 15); // Cards 1 through 15

        // Template and game
        $template = GameTemplate::where('slug', 'single_line')->firstOrFail();
        $this->game = $this->lifecycleService->createFromTemplate($template, $this->company, [
            'name' => 'Championship Room',
            'entry_fee' => 1000, // $10.00 per card
        ]);
    }

    public function test_admin_can_assign_card_to_walk_in_cash_player_without_account(): void
    {
        $this->lifecycleService->openGame($this->game);

        $response = $this->actingAs($this->adminUser)
            ->post("/c/{$this->company->slug}/admin/games/{$this->game->id}/assign-card", [
                'card_number' => 5,
                'is_walkin' => true,
                'guest_identifier' => 'Walk-in Table 7 / Dave',
            ]);

        $response->assertSessionHas('success');

        $assignedGameCard = GameCard::where('game_id', $this->game->id)
            ->whereNull('user_id')
            ->where('guest_identifier', 'Walk-in Table 7 / Dave')
            ->first();

        $this->assertNotNull($assignedGameCard);
        $this->assertSame(5, $assignedGameCard->card->card_number);
        $this->assertTrue($assignedGameCard->isWalkIn());
        $this->assertSame('Walk-in Table 7 / Dave', $assignedGameCard->playerDisplayName());

        // Inventory card status should be 'assigned'
        $card5 = BingoCard::where('company_id', $this->company->id)->where('card_number', 5)->first();
        $this->assertSame(BingoCard::STATUS_ASSIGNED, $card5->status);

        // Game player record created with null user_id
        $gamePlayer = GamePlayer::where('game_id', $this->game->id)
            ->whereNull('user_id')
            ->first();
        $this->assertNotNull($gamePlayer);
        $this->assertSame('Walk-in Table 7 / Dave', $gamePlayer->guest_identifier);
        $this->assertTrue($gamePlayer->isWalkIn());
    }

    public function test_game_activation_blocked_when_company_credit_balance_is_zero(): void
    {
        // Deplete company credit balance to zero
        $this->company->update(['credit_balance' => 0]);

        $response = $this->actingAs($this->adminUser)
            ->patch("/c/{$this->company->slug}/admin/games/{$this->game->id}/status", [
                'status' => Game::STATUS_OPEN,
            ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Insufficient platform credit', session('error'));

        // Game status should still be draft
        $this->game->refresh();
        $this->assertSame(Game::STATUS_DRAFT, $this->game->status);
    }

    public function test_pot_and_revenue_sharing_split_calculated_on_game_completion(): void
    {
        // Set revenue percentages: 75% winner, 20% platform fee from house gross
        PlatformSetting::set('revenue_settings', [
            'winner_share_percentage' => 75.0,
            'platform_fee_percentage' => 20.0,
        ]);

        $this->lifecycleService->openGame($this->game);

        // Assign 10 players: 8 online players and 2 walk-in cash players
        // Card entry fee is $10.00 (1000 cents) -> 10 * 1000 = 10000 cents ($100.00) total pot
        for ($i = 1; $i <= 8; $i++) {
            $user = User::factory()->create([
                'company_id' => $this->company->id,
                'status' => 'active',
            ]);
            $user->assignRole(Role::PLAYER);
            $this->assignmentService->assignSpecificCardToPlayer($this->game, $user, $i);
        }

        $this->assignmentService->assignWalkInCard($this->game, 9, 'Walk-in Cash Player A');
        $this->assignmentService->assignWalkInCard($this->game, 10, 'Walk-in Cash Player B');

        $initialCompanyCredit = $this->company->credit_balance; // 50000 cents ($500.00)

        // Start game
        $this->lifecycleService->startGame($this->game);

        // Complete game
        $this->lifecycleService->completeGame($this->game);

        $this->game->refresh();
        $this->company->refresh();

        // Total pot = 10 cards * 1000 = 10000 cents ($100.00)
        $this->assertSame(10000, $this->game->total_pot);

        // Winner payout = 75% of 10000 = 7500 cents ($75.00)
        $this->assertSame(7500, $this->game->winner_payout_total);

        // House gross cut = 10000 - 7500 = 2500 cents ($25.00)
        $this->assertSame(2500, $this->game->house_gross_cut);

        // Platform fee = 20% of 2500 = 500 cents ($5.00)
        $this->assertSame(500, $this->game->platform_fee);

        // Company net cut = 2500 - 500 = 2000 cents ($20.00)
        $this->assertSame(2000, $this->game->company_net_cut);

        // Company credit balance should be debited by platform fee (500 cents)
        $this->assertSame($initialCompanyCredit - 500, $this->company->credit_balance);

        // Platform fee transaction should be recorded in transactions table
        $platformFeeTx = Transaction::where('company_id', $this->company->id)
            ->where('reference_type', Game::class)
            ->where('reference_id', $this->game->id)
            ->where('type', Transaction::TYPE_PLATFORM_FEE)
            ->first();

        $this->assertNotNull($platformFeeTx);
        $this->assertSame(500, $platformFeeTx->amount);
    }

    public function test_walk_in_winner_claims_bingo_and_is_paid_in_cash_without_ledger_error(): void
    {
        $this->lifecycleService->openGame($this->game);
        $gameCard = $this->assignmentService->assignWalkInCard($this->game, 3, 'Walk-in Guest Bob');

        $this->lifecycleService->startGame($this->game);

        // Grid is a 5x5 array on the active version
        $grid = $gameCard->version->grid;
        $row0 = $grid[0]; // 5 numbers in top horizontal row

        foreach ($row0 as $idx => $num) {
            GameCall::create([
                'game_id' => $this->game->id,
                'sequence_index' => $idx + 1,
                'ball_number' => $num,
                'letter' => GameCall::getLetterForNumber($num),
                'called_at' => now(),
            ]);
        }

        /** @var BingoVerificationService $verificationService */
        $verificationService = app(BingoVerificationService::class);
        $claimResult = $verificationService->claimBingo($this->game, $gameCard, null);

        $this->assertTrue($claimResult['success']);
        /** @var GameWinner $winner */
        $winner = $claimResult['winner'];
        $this->assertNotNull($winner);
        $this->assertTrue($winner->isWalkIn());
        $this->assertSame(GameWinner::PAYOUT_STATUS_PAID, $winner->payout_status);
        $this->assertSame('Walk-in Guest Bob', $winner->winnerDisplayName());
    }

    public function test_platform_owner_can_approve_company_credit_purchase_request(): void
    {
        // Platform payment account
        $platformAccount = PaymentAccount::create([
            'company_id' => null,
            'provider_name' => 'National Bank',
            'account_name' => 'Nobingo HQ',
            'account_number' => '1000998877',
            'is_active' => true,
        ]);

        // Company admin submits credit purchase request
        $file = UploadedFile::fake()->create('receipt.png', 100, 'image/png');

        $response = $this->actingAs($this->adminUser)
            ->post("/c/{$this->company->slug}/admin/credits/buy", [
                'payment_account_id' => $platformAccount->id,
                'amount' => 200, // $200.00
                'reference_number' => 'WIRE-992211',
                'receipt' => $file,
                'notes' => 'Monthly credit topup',
            ]);

        $response->assertSessionHas('success');

        $creditRequest = DepositRequest::where('company_id', $this->company->id)
            ->where('type', DepositRequest::TYPE_COMPANY_CREDIT)
            ->first();

        $this->assertNotNull($creditRequest);
        $this->assertSame(20000, $creditRequest->amount); // 20000 cents = $200
        $this->assertSame(DepositRequest::STATUS_PENDING, $creditRequest->status);

        $creditBefore = $this->company->fresh()->credit_balance;

        // Platform owner approves the request
        $approvalResponse = $this->actingAs($this->platformOwner)
            ->post("/platform/deposit-requests/{$creditRequest->id}/approve", [
                'notes' => 'Payment received via bank wire',
            ]);

        $approvalResponse->assertSessionHas('success');

        $creditRequest->refresh();
        $this->assertSame(DepositRequest::STATUS_APPROVED, $creditRequest->status);
        $this->assertSame($this->platformOwner->id, $creditRequest->reviewed_by);

        // Company credit balance increased by 20000 cents
        $this->assertSame($creditBefore + 20000, $this->company->fresh()->credit_balance);

        // Credit purchase transaction logged
        $this->assertTrue(
            Transaction::where('company_id', $this->company->id)
                ->where('type', Transaction::TYPE_CREDIT_PURCHASE)
                ->where('amount', 20000)
                ->exists()
        );
    }

    public function test_company_admin_can_approve_player_deposit_request(): void
    {
        // Company payment account
        $companyAccount = PaymentAccount::create([
            'company_id' => $this->company->id,
            'provider_name' => 'Telebirr',
            'account_name' => 'Royal Bingo',
            'account_number' => '+251911223344',
            'is_active' => true,
        ]);

        $file = UploadedFile::fake()->create('player_slip.png', 100, 'image/png');

        // Player submits deposit request
        $response = $this->actingAs($this->player)
            ->post("/c/{$this->company->slug}/wallet/deposit-request", [
                'payment_account_id' => $companyAccount->id,
                'amount' => 35, // $35.00
                'reference_number' => 'TB-789012',
                'receipt' => $file,
                'notes' => 'Deposit for weekend games',
            ]);

        $response->assertSessionHas('success');

        $depositRequest = DepositRequest::where('company_id', $this->company->id)
            ->where('user_id', $this->player->id)
            ->where('type', DepositRequest::TYPE_PLAYER_DEPOSIT)
            ->first();

        $this->assertNotNull($depositRequest);
        $this->assertSame(3500, $depositRequest->amount);
        $this->assertSame(DepositRequest::STATUS_PENDING, $depositRequest->status);

        $playerBalanceBefore = $this->player->fresh()->balance;

        // Company admin approves deposit request
        $approveResponse = $this->actingAs($this->adminUser)
            ->post("/c/{$this->company->slug}/admin/deposit-requests/{$depositRequest->id}/approve", [
                'notes' => 'Confirmed on Telebirr app',
            ]);

        $approveResponse->assertSessionHas('success');

        $depositRequest->refresh();
        $this->assertSame(DepositRequest::STATUS_APPROVED, $depositRequest->status);

        // Player wallet credited by $35.00 (3500 cents)
        $this->assertSame($playerBalanceBefore + 3500, $this->player->fresh()->balance);

        // Deposit transaction logged
        $this->assertTrue(
            Transaction::where('company_id', $this->company->id)
                ->where('user_id', $this->player->id)
                ->where('type', Transaction::TYPE_DEPOSIT)
                ->where('amount', 3500)
                ->exists()
        );
    }

    public function test_company_admin_can_perform_direct_manual_top_up_for_player(): void
    {
        $playerBalanceBefore = $this->player->balance;

        $response = $this->actingAs($this->adminUser)
            ->post("/c/{$this->company->slug}/admin/players/manual-deposit", [
                'user_id' => $this->player->id,
                'amount' => 50, // $50.00
                'notes' => 'Cash received at front desk',
            ]);

        $response->assertSessionHas('success');

        // Player balance should increase by 5000 cents ($50.00)
        $this->player->refresh();
        $this->assertSame($playerBalanceBefore + 5000, $this->player->balance);

        // Transaction logged
        $this->assertTrue(
            Transaction::where('company_id', $this->company->id)
                ->where('user_id', $this->player->id)
                ->where('type', Transaction::TYPE_DEPOSIT)
                ->where('amount', 5000)
                ->exists()
        );
    }
}
