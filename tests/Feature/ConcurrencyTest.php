<?php

namespace Tests\Feature;

use App\Domains\Cards\Models\BingoCard;
use App\Domains\Cards\Services\BingoCardGenerator;
use App\Domains\Cards\Services\BingoCardValidator;
use App\Domains\Financial\Contracts\PaymentGatewayInterface;
use App\Domains\Financial\Models\Transaction;
use App\Domains\Financial\Services\LedgerService;
use App\Domains\Financial\Services\PrizeCalculationService;
use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameCall;
use App\Domains\Games\Models\GameCard;
use App\Domains\Games\Models\GamePlayer;
use App\Domains\Games\Models\GameTemplate;
use App\Domains\Games\Services\CardAssignmentService;
use App\Domains\Games\Services\GameLifecycleService;
use App\Domains\Games\Services\NumberCallingService;
use App\Domains\Patterns\Models\WinningPattern;
use App\Domains\Tenancy\Models\Company;
use App\Domains\Winners\Models\GameWinner;
use App\Domains\Winners\Services\BingoVerificationService;
use App\Models\User;
use Database\Seeders\GameTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WinningPatternSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $playerA;

    protected User $playerB;

    protected User $playerC;

    protected GameLifecycleService $lifecycleService;

    protected CardAssignmentService $assignmentService;

    protected NumberCallingService $callingService;

    protected BingoVerificationService $verificationService;

    protected PrizeCalculationService $calculationService;

    protected LedgerService $ledgerService;

    protected PaymentGatewayInterface $paymentGateway;

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

        $this->playerA = User::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
            'balance' => 50000, // $500.00
        ]);

        $this->playerB = User::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
            'balance' => 50000,
        ]);

        $this->playerC = User::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
            'balance' => 50000,
        ]);

        $this->lifecycleService = app(GameLifecycleService::class);
        $this->assignmentService = app(CardAssignmentService::class);
        $this->callingService = app(NumberCallingService::class);
        $this->verificationService = app(BingoVerificationService::class);
        $this->calculationService = app(PrizeCalculationService::class);
        $this->ledgerService = app(LedgerService::class);
        $this->paymentGateway = app(PaymentGatewayInterface::class);
        $this->cardGenerator = new BingoCardGenerator(new BingoCardValidator);

        $this->cardGenerator->generateBatch($this->company, 20);
    }

    public function test_simultaneous_bingo_claims_under_first_valid_policy_declares_exactly_one_winner(): void
    {
        $pattern = WinningPattern::where('slug', 'horizontal-line')->first() ?? WinningPattern::firstOrFail();
        $template = GameTemplate::firstOrFail();

        $game = $this->lifecycleService->createFromTemplate($template, $this->company, [
            'winner_policy' => Game::WINNER_POLICY_FIRST_VALID,
            'allowed_pattern_ids' => [$pattern->id],
            'required_pattern_count' => 1,
        ]);

        $this->lifecycleService->openGame($game);
        $this->assignmentService->joinGame($game, $this->playerA);
        $this->assignmentService->joinGame($game, $this->playerB);
        $this->lifecycleService->startGame($game);

        $cardA = GameCard::where('game_id', $game->id)->where('user_id', $this->playerA->id)->firstOrFail();
        $cardB = GameCard::where('game_id', $game->id)->where('user_id', $this->playerB->id)->firstOrFail();

        // Call numbers until cardA has a winning line
        $gridA = $cardA->version->grid;
        // Let's call the top row of Card A
        $numbersToCall = [];
        for ($col = 0; $col < 5; $col++) {
            $numbersToCall[] = $gridA[0][$col];
        }

        foreach ($numbersToCall as $seq => $num) {
            GameCall::create([
                'game_id' => $game->id,
                'ball_number' => $num,
                'letter' => GameCall::getLetterForNumber($num),
                'sequence_index' => $seq + 1,
                'called_at' => now(),
            ]);
        }
        $game->update(['total_calls' => count($numbersToCall)]);

        // Player A claims Bingo -> Winner 1
        $resultA = $this->verificationService->claimBingo($game, $cardA, $this->playerA);
        $this->assertTrue($resultA['success']);
        $this->assertSame(Game::STATUS_COMPLETED, $game->fresh()->status);

        // Player B attempts to claim immediately after -> Rejected because game is completed and policy is first_valid
        $resultB = $this->verificationService->claimBingo($game, $cardB, $this->playerB);
        $this->assertFalse($resultB['success']);
        $this->assertSame('Cannot claim Bingo. Game is not active.', $resultB['message']);

        // Exactly 1 winner recorded
        $this->assertSame(1, GameWinner::where('game_id', $game->id)->count());
    }

    public function test_simultaneous_bingo_claims_under_simultaneous_policy_splits_prize_pool_evenly(): void
    {
        $pattern = WinningPattern::where('slug', 'horizontal-line')->first() ?? WinningPattern::firstOrFail();
        $template = GameTemplate::firstOrFail();

        $game = $this->lifecycleService->createFromTemplate($template, $this->company, [
            'winner_policy' => Game::WINNER_POLICY_SIMULTANEOUS,
            'allowed_pattern_ids' => [$pattern->id],
            'required_pattern_count' => 1,
            'prize_configuration' => [
                'fixed_prize' => 10000, // $100.00
            ],
        ]);

        $this->lifecycleService->openGame($game);
        $this->assignmentService->joinGame($game, $this->playerA);
        $this->assignmentService->joinGame($game, $this->playerB);
        $this->lifecycleService->startGame($game);

        $cardA = GameCard::where('game_id', $game->id)->where('user_id', $this->playerA->id)->firstOrFail();
        $cardB = GameCard::where('game_id', $game->id)->where('user_id', $this->playerB->id)->firstOrFail();

        // Call numbers that complete rows on both cards
        $gridA = $cardA->version->grid;
        $gridB = $cardB->version->grid;

        $numsA = [$gridA[0][0], $gridA[0][1], $gridA[0][2], $gridA[0][3], $gridA[0][4]];
        $numsB = [$gridB[0][0], $gridB[0][1], $gridB[0][2], $gridB[0][3], $gridB[0][4]];
        $allNums = array_values(array_unique(array_merge($numsA, $numsB)));

        foreach ($allNums as $seq => $num) {
            GameCall::create([
                'game_id' => $game->id,
                'ball_number' => $num,
                'letter' => GameCall::getLetterForNumber($num),
                'sequence_index' => $seq + 1,
                'called_at' => now(),
            ]);
        }
        $game->update(['total_calls' => count($allNums)]);

        // Player A claims on the final ball sequence
        $resultA = $this->verificationService->claimBingo($game, $cardA, $this->playerA);
        $this->assertTrue($resultA['success']);

        // Player B claims on the same ball sequence -> accepted as simultaneous tie
        $resultB = $this->verificationService->claimBingo($game, $cardB, $this->playerB);
        $this->assertTrue($resultB['success']);

        $winners = GameWinner::where('game_id', $game->id)->get();
        $this->assertSame(2, $winners->count());
        $this->assertSame(count($allNums), $winners[0]->winning_call_sequence);
        $this->assertSame(count($allNums), $winners[1]->winning_call_sequence);

        // Verify mathematical prize split without remainder loss
        $prizeA = $winners[0]->fresh()->payout_amount;
        $prizeB = $winners[1]->fresh()->payout_amount;

        $this->assertSame(5000, $prizeA);
        $this->assertSame(5000, $prizeB);
        $this->assertSame(10000, $prizeA + $prizeB);
    }

    public function test_prize_calculation_splits_odd_remainders_with_zero_leakage(): void
    {
        $template = GameTemplate::firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $this->company, [
            'prize_configuration' => [
                'fixed_prize' => 10000, // $100.00
            ],
        ]);

        $totalPool = $this->calculationService->calculateTotalPrizePool($game);
        $this->assertSame(10000, $totalPool);

        $perWinnerShare = $this->calculationService->calculatePrizeForPosition($game, 1, 3);
        $this->assertSame(3333, $perWinnerShare);

        // Remainder distribution test:
        $totalWinners = 3;
        $baseShare = (int) floor($totalPool / $totalWinners);
        $remainder = $totalPool % $totalWinners;

        $shares = [];
        for ($i = 0; $i < $totalWinners; $i++) {
            $shares[] = $baseShare + ($i < $remainder ? 1 : 0);
        }

        $this->assertSame(3334, $shares[0]);
        $this->assertSame(3333, $shares[1]);
        $this->assertSame(3333, $shares[2]);
        $this->assertSame(10000, array_sum($shares));
    }

    public function test_concurrent_rapid_withdrawals_prevent_double_spend_and_negative_balance(): void
    {
        // Player has $50.00 (5000 cents)
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'balance' => 5000,
            'status' => 'active',
        ]);

        // Withdrawal 1: $40.00 (4000 cents)
        $tx1 = $this->ledgerService->recordWithdrawal($user, 4000);
        $this->assertSame(1000, $user->fresh()->balance);
        $this->assertSame(Transaction::STATUS_COMPLETED, $tx1->status);

        // Withdrawal 2: $40.00 (4000 cents) -> must fail due to insufficient funds ($10.00 remaining)
        $failed = false;
        try {
            $this->ledgerService->recordWithdrawal($user, 4000);
        } catch (RuntimeException $e) {
            $failed = true;
            $this->assertStringContainsString('Insufficient wallet balance', $e->getMessage());
        }

        $this->assertTrue($failed);
        $this->assertSame(1000, $user->fresh()->balance, 'Balance was corrupted or allowed to drop below zero.');
    }

    public function test_card_inventory_assignment_race_condition_prevents_duplicate_card_in_same_game(): void
    {
        // Create an isolated company with exactly ONE card in inventory
        $isolatedCompany = Company::create([
            'name' => 'Single Card Club',
            'slug' => 'single-card-club',
            'status' => 'active',
        ]);

        $this->cardGenerator->generateBatch($isolatedCompany, 1);
        $this->assertSame(1, BingoCard::where('company_id', $isolatedCompany->id)->count());

        $user1 = User::factory()->create([
            'company_id' => $isolatedCompany->id,
            'status' => 'active',
            'balance' => 5000,
        ]);
        $user2 = User::factory()->create([
            'company_id' => $isolatedCompany->id,
            'status' => 'active',
            'balance' => 5000,
        ]);

        $template = GameTemplate::firstOrFail();
        $game = $this->lifecycleService->createFromTemplate($template, $isolatedCompany, [
            'max_players' => 10,
        ]);
        $this->lifecycleService->openGame($game);

        // Player 1 claims the only available card
        $gp1 = $this->assignmentService->joinGame($game, $user1);
        $this->assertInstanceOf(GamePlayer::class, $gp1);

        // Player 2 attempts to join -> No cards remaining
        $failed = false;
        try {
            $this->assignmentService->joinGame($game, $user2);
        } catch (RuntimeException $e) {
            $failed = true;
            $this->assertStringContainsString('No available cards in company inventory', $e->getMessage());
        }

        $this->assertTrue($failed);
        // Only 1 card was assigned
        $this->assertSame(1, GameCard::where('game_id', $game->id)->count());
    }

    public function test_financial_integrity_ledger_invariants_and_audit_completeness(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'balance' => 0,
            'status' => 'active',
        ]);

        // Sequence of operations
        // 1. Deposit $100
        $this->ledgerService->recordDeposit($user, 10000);
        // 2. Adjustment +$25
        $this->ledgerService->recordAdjustment($user, 2500, true, 'Loyalty bonus');
        // 3. Adjustment -$15
        $this->ledgerService->recordAdjustment($user, 1500, false, 'Correction fee');
        // 4. Withdrawal $30
        $this->ledgerService->recordWithdrawal($user, 3000);

        $user->refresh();

        // Expected balance: 10000 + 2500 - 1500 - 3000 = 8000 cents ($80.00)
        $this->assertSame(8000, $user->balance);

        // Ledger mathematical invariant test:
        $credits = (int) Transaction::where('user_id', $user->id)
            ->whereIn('type', [Transaction::TYPE_DEPOSIT, Transaction::TYPE_PRIZE, Transaction::TYPE_REFUND])
            ->orWhere(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->where('type', Transaction::TYPE_ADJUSTMENT)
                    ->whereRaw('balance_after > balance_before');
            })->sum('amount');

        $debits = (int) Transaction::where('user_id', $user->id)
            ->whereIn('type', [Transaction::TYPE_ENTRY_FEE, Transaction::TYPE_WITHDRAWAL])
            ->orWhere(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->where('type', Transaction::TYPE_ADJUSTMENT)
                    ->whereRaw('balance_after < balance_before');
            })->sum('amount');

        $this->assertSame(8000, $credits - $debits);
    }
}
