<?php

namespace App\Domains\Reports\Services;

use App\Domains\Auth\Models\Role;
use App\Domains\Financial\Models\Transaction;
use App\Domains\Games\Models\Game;
use App\Domains\Tenancy\Models\Company;
use App\Domains\Winners\Models\GameWinner;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportingService
{
    /**
     * Generate comprehensive executive and manager reporting metrics for a tenant company.
     *
     * @return array<string, mixed>
     */
    public function getCompanyReport(
        Company $company,
        ?int $managerId = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?User $currentUser = null
    ): array {
        // Enforce Game Manager isolation: Game Managers can only see their own metrics
        $isGameManager = $currentUser && $currentUser->isGameManager() && ! $currentUser->isCompanyAdmin() && ! $currentUser->isPlatformOwner();
        if ($isGameManager) {
            $managerId = $currentUser->id;
        }

        $startDateTime = $startDate ? Carbon::parse($startDate)->startOfDay() : null;
        $endDateTime = $endDate ? Carbon::parse($endDate)->endOfDay() : null;

        // 1. Game Performance Metrics
        $gamesQuery = Game::where('company_id', $company->id);
        if ($managerId !== null) {
            $gamesQuery->where('created_by', $managerId);
        }
        if ($startDateTime) {
            $gamesQuery->where('created_at', '>=', $startDateTime);
        }
        if ($endDateTime) {
            $gamesQuery->where('created_at', '<=', $endDateTime);
        }

        $totalGames = (clone $gamesQuery)->count();
        $completedGames = (clone $gamesQuery)->where('status', Game::STATUS_COMPLETED)->count();
        $cancelledGames = (clone $gamesQuery)->where('status', Game::STATUS_CANCELLED)->count();
        $activeGames = (clone $gamesQuery)->whereIn('status', [
            Game::STATUS_ACTIVE,
            Game::STATUS_OPEN,
            Game::STATUS_STARTING,
            Game::STATUS_PAUSED,
        ])->count();

        $finishedCount = $completedGames + $cancelledGames;
        $completionRate = $finishedCount > 0 ? round(($completedGames / $finishedCount) * 100, 1) : 100.0;

        // Average calls to win
        $winnersQuery = GameWinner::where('company_id', $company->id);
        if ($managerId !== null) {
            $winnersQuery->whereHas('game', fn ($g) => $g->where('created_by', $managerId));
        }
        if ($startDateTime) {
            $winnersQuery->where('created_at', '>=', $startDateTime);
        }
        if ($endDateTime) {
            $winnersQuery->where('created_at', '<=', $endDateTime);
        }
        $avgCallsToWin = (float) ((clone $winnersQuery)->avg('winning_call_sequence') ?? 0);

        // 2. Financial Metrics (Period Scoped)
        $txBaseQuery = Transaction::where('company_id', $company->id);
        if ($managerId !== null) {
            $txBaseQuery->where('game_manager_id', $managerId);
        }
        if ($startDateTime) {
            $txBaseQuery->where('created_at', '>=', $startDateTime);
        }
        if ($endDateTime) {
            $txBaseQuery->where('created_at', '<=', $endDateTime);
        }

        $entryFees = (int) (clone $txBaseQuery)->where('type', Transaction::TYPE_ENTRY_FEE)->sum('amount');
        $prizesPaid = (int) (clone $txBaseQuery)->where('type', Transaction::TYPE_PRIZE)->sum('amount');
        $refundsIssued = (int) (clone $txBaseQuery)->where('type', Transaction::TYPE_REFUND)->sum('amount');
        $deposits = (int) (clone $txBaseQuery)->where('type', Transaction::TYPE_DEPOSIT)->sum('amount');
        $withdrawals = (int) (clone $txBaseQuery)->where('type', Transaction::TYPE_WITHDRAWAL)->sum('amount');

        $netHouseEarnings = $entryFees - ($prizesPaid + $refundsIssued);
        $houseMargin = $entryFees > 0 ? round(($netHouseEarnings / $entryFees) * 100, 1) : 0.0;

        // 3. Walk-in Cash Flow Metrics
        $walkInSalesQuery = (clone $txBaseQuery)->where('type', Transaction::TYPE_ENTRY_FEE)->whereNull('user_id');
        $walkInSalesAmount = (int) (clone $walkInSalesQuery)->sum('amount');
        $walkInSalesCount = (clone $walkInSalesQuery)->count();

        $walkInPrizesQuery = (clone $txBaseQuery)->where('type', Transaction::TYPE_PRIZE)->whereNull('user_id');
        $walkInPrizesAmount = (int) (clone $walkInPrizesQuery)->sum('amount');
        $walkInPrizesCount = (clone $walkInPrizesQuery)->count();

        $walkInNetCash = $walkInSalesAmount - $walkInPrizesAmount;

        // 4. Cash-on-Hand / Drawer Reconcile (Inflow = Walk-in Sales + Deposits; Outflow = Walk-in Prizes + Withdrawals)
        $periodCashInflows = $walkInSalesAmount + $deposits;
        $periodCashOutflows = $walkInPrizesAmount + $withdrawals;
        $periodNetCashFlow = $periodCashInflows - $periodCashOutflows;

        // All-Time Cash-on-Hand calculation for the manager/company
        $allTimeTxQuery = Transaction::where('company_id', $company->id);
        if ($managerId !== null) {
            $allTimeTxQuery->where('game_manager_id', $managerId);
        }
        $allTimeCashIn = (int) (clone $allTimeTxQuery)->where(function ($q) {
            $q->where(fn ($sq) => $sq->where('type', Transaction::TYPE_ENTRY_FEE)->whereNull('user_id'))
                ->orWhere('type', Transaction::TYPE_DEPOSIT);
        })->sum('amount');

        $allTimeCashOut = (int) (clone $allTimeTxQuery)->where(function ($q) {
            $q->where(fn ($sq) => $sq->where('type', Transaction::TYPE_PRIZE)->whereNull('user_id'))
                ->orWhere('type', Transaction::TYPE_WITHDRAWAL);
        })->sum('amount');

        $totalCashOnHand = $allTimeCashIn - $allTimeCashOut;

        // 5. Manager Breakdown Table (visible to Company Admin only)
        $managersBreakdown = [];
        if (! $isGameManager) {
            $managers = User::where('company_id', $company->id)
                ->whereHas('roles', fn ($q) => $q->where('slug', Role::GAME_MANAGER))
                ->orderBy('name')
                ->get();

            $managersBreakdown = $managers->map(function (User $mgr) use ($company, $startDateTime, $endDateTime) {
                $mgrTx = Transaction::where('company_id', $company->id)->where('game_manager_id', $mgr->id);
                $periodTx = clone $mgrTx;
                if ($startDateTime) {
                    $periodTx->where('created_at', '>=', $startDateTime);
                }
                if ($endDateTime) {
                    $periodTx->where('created_at', '<=', $endDateTime);
                }

                $wSales = (int) (clone $periodTx)->where('type', Transaction::TYPE_ENTRY_FEE)->whereNull('user_id')->sum('amount');
                $wSalesCount = (clone $periodTx)->where('type', Transaction::TYPE_ENTRY_FEE)->whereNull('user_id')->count();

                $cDeposits = (int) (clone $periodTx)->where('type', Transaction::TYPE_DEPOSIT)->sum('amount');
                $cDepositsCount = (clone $periodTx)->where('type', Transaction::TYPE_DEPOSIT)->count();

                $wPrizes = (int) (clone $periodTx)->where('type', Transaction::TYPE_PRIZE)->whereNull('user_id')->sum('amount');
                $wPrizesCount = (clone $periodTx)->where('type', Transaction::TYPE_PRIZE)->whereNull('user_id')->count();

                $cWithdrawals = (int) (clone $periodTx)->where('type', Transaction::TYPE_WITHDRAWAL)->sum('amount');
                $cWithdrawalsCount = (clone $periodTx)->where('type', Transaction::TYPE_WITHDRAWAL)->count();

                $pInflow = $wSales + $cDeposits;
                $pOutflow = $wPrizes + $cWithdrawals;
                $pNet = $pInflow - $pOutflow;

                // All-time cash on hand for this manager
                $allIn = (int) (clone $mgrTx)->where(function ($q) {
                    $q->where(fn ($sq) => $sq->where('type', Transaction::TYPE_ENTRY_FEE)->whereNull('user_id'))
                        ->orWhere('type', Transaction::TYPE_DEPOSIT);
                })->sum('amount');

                $allOut = (int) (clone $mgrTx)->where(function ($q) {
                    $q->where(fn ($sq) => $sq->where('type', Transaction::TYPE_PRIZE)->whereNull('user_id'))
                        ->orWhere('type', Transaction::TYPE_WITHDRAWAL);
                })->sum('amount');

                $allCash = $allIn - $allOut;

                return [
                    'id' => $mgr->id,
                    'name' => $mgr->name,
                    'email' => $mgr->email,
                    'walkin_sales' => $wSales,
                    'formatted_walkin_sales' => '$'.number_format($wSales / 100, 2),
                    'walkin_sales_count' => $wSalesCount,
                    'deposits' => $cDeposits,
                    'formatted_deposits' => '$'.number_format($cDeposits / 100, 2),
                    'deposits_count' => $cDepositsCount,
                    'walkin_prizes' => $wPrizes,
                    'formatted_walkin_prizes' => '$'.number_format($wPrizes / 100, 2),
                    'walkin_prizes_count' => $wPrizesCount,
                    'withdrawals' => $cWithdrawals,
                    'formatted_withdrawals' => '$'.number_format($cWithdrawals / 100, 2),
                    'withdrawals_count' => $cWithdrawalsCount,
                    'period_inflow' => $pInflow,
                    'formatted_period_inflow' => '$'.number_format($pInflow / 100, 2),
                    'period_outflow' => $pOutflow,
                    'formatted_period_outflow' => '$'.number_format($pOutflow / 100, 2),
                    'period_net' => $pNet,
                    'formatted_period_net' => ($pNet < 0 ? '-' : '+').'$'.number_format(abs($pNet) / 100, 2),
                    'total_cash_on_hand' => $allCash,
                    'formatted_total_cash_on_hand' => ($allCash < 0 ? '-' : '').'$'.number_format(abs($allCash) / 100, 2),
                ];
            })->all();
        }

        // 6. Player Metrics
        $playerQuery = User::where('company_id', $company->id)
            ->whereHas('roles', fn ($q) => $q->where('slug', Role::PLAYER));

        if ($managerId !== null) {
            $playerQuery->whereHas('playerManagers', fn ($q) => $q->where('game_manager_id', $managerId));
        }

        $totalPlayers = (clone $playerQuery)->count();
        $activePlayers = (clone $playerQuery)->where('status', 'active')->count();
        $suspendedPlayers = (clone $playerQuery)->where('status', 'suspended')->count();

        // Top 5 Winners
        $topWinnersQuery = GameWinner::where('company_id', $company->id)
            ->whereNotNull('user_id');
        if ($managerId !== null) {
            $topWinnersQuery->whereHas('game', fn ($g) => $g->where('created_by', $managerId));
        }
        if ($startDateTime) {
            $topWinnersQuery->where('created_at', '>=', $startDateTime);
        }
        if ($endDateTime) {
            $topWinnersQuery->where('created_at', '<=', $endDateTime);
        }

        $topWinners = $topWinnersQuery
            ->select('user_id', DB::raw('SUM(payout_amount) as total_won'), DB::raw('COUNT(id) as win_count'))
            ->groupBy('user_id')
            ->orderByDesc('total_won')
            ->limit(5)
            ->with('user:id,name,email')
            ->get()
            ->map(fn ($w) => [
                'user_id' => $w->user_id,
                'name' => $w->user->name ?? 'Player',
                'email' => $w->user->email ?? '',
                'total_won' => (int) $w->total_won,
                'formatted_total_won' => '$'.number_format(((int) $w->total_won) / 100, 2),
                'win_count' => (int) $w->win_count,
            ]);

        return [
            'is_game_manager' => $isGameManager,
            'selected_manager_id' => $managerId,
            'filters' => [
                'start_date' => $startDate ?? '',
                'end_date' => $endDate ?? '',
                'manager_id' => $managerId ? (string) $managerId : '',
            ],
            'games' => [
                'total' => $totalGames,
                'completed' => $completedGames,
                'cancelled' => $cancelledGames,
                'active' => $activeGames,
                'completion_rate' => $completionRate,
                'avg_calls_to_win' => round($avgCallsToWin, 1),
            ],
            'financial' => [
                'entry_fees' => $entryFees,
                'formatted_entry_fees' => '$'.number_format($entryFees / 100, 2),
                'prizes_paid' => $prizesPaid,
                'formatted_prizes_paid' => '$'.number_format($prizesPaid / 100, 2),
                'refunds_issued' => $refundsIssued,
                'formatted_refunds_issued' => '$'.number_format($refundsIssued / 100, 2),
                'deposits' => $deposits,
                'formatted_deposits' => '$'.number_format($deposits / 100, 2),
                'withdrawals' => $withdrawals,
                'formatted_withdrawals' => '$'.number_format($withdrawals / 100, 2),
                'net_house_earnings' => $netHouseEarnings,
                'formatted_net_house_earnings' => ($netHouseEarnings < 0 ? '-' : '+').'$'.number_format(abs($netHouseEarnings) / 100, 2),
                'house_margin' => $houseMargin,
            ],
            'walkin' => [
                'sales_amount' => $walkInSalesAmount,
                'formatted_sales_amount' => '$'.number_format($walkInSalesAmount / 100, 2),
                'sales_count' => $walkInSalesCount,
                'prizes_amount' => $walkInPrizesAmount,
                'formatted_prizes_amount' => '$'.number_format($walkInPrizesAmount / 100, 2),
                'prizes_count' => $walkInPrizesCount,
                'net_cash' => $walkInNetCash,
                'formatted_net_cash' => ($walkInNetCash < 0 ? '-' : '+').'$'.number_format(abs($walkInNetCash) / 100, 2),
            ],
            'cash_on_hand' => [
                'period_inflow' => $periodCashInflows,
                'formatted_period_inflow' => '$'.number_format($periodCashInflows / 100, 2),
                'period_outflow' => $periodCashOutflows,
                'formatted_period_outflow' => '$'.number_format($periodCashOutflows / 100, 2),
                'period_net_flow' => $periodNetCashFlow,
                'formatted_period_net_flow' => ($periodNetCashFlow < 0 ? '-' : '+').'$'.number_format(abs($periodNetCashFlow) / 100, 2),
                'total_cash_on_hand' => $totalCashOnHand,
                'formatted_total_cash_on_hand' => ($totalCashOnHand < 0 ? '-' : '').'$'.number_format(abs($totalCashOnHand) / 100, 2),
            ],
            'managers_breakdown' => $managersBreakdown,
            'players' => [
                'total' => $totalPlayers,
                'active' => $activePlayers,
                'suspended' => $suspendedPlayers,
                'top_winners' => $topWinners,
            ],
        ];
    }
}
