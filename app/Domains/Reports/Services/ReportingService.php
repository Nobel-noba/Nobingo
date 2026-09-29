<?php

namespace App\Domains\Reports\Services;

use App\Domains\Auth\Models\Role;
use App\Domains\Financial\Models\Transaction;
use App\Domains\Games\Models\Game;
use App\Domains\Tenancy\Models\Company;
use App\Domains\Winners\Models\GameWinner;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReportingService
{
    /**
     * Generate comprehensive executive reporting metrics for a tenant company.
     *
     * @return array<string, mixed>
     */
    public function getCompanyReport(Company $company): array
    {
        // 1. Game Performance Metrics
        $totalGames = Game::where('company_id', $company->id)->count();
        $completedGames = Game::where('company_id', $company->id)->where('status', Game::STATUS_COMPLETED)->count();
        $cancelledGames = Game::where('company_id', $company->id)->where('status', Game::STATUS_CANCELLED)->count();
        $activeGames = Game::where('company_id', $company->id)->whereIn('status', [
            Game::STATUS_ACTIVE,
            Game::STATUS_OPEN,
            Game::STATUS_STARTING,
            Game::STATUS_PAUSED,
        ])->count();

        $finishedCount = $completedGames + $cancelledGames;
        $completionRate = $finishedCount > 0 ? round(($completedGames / $finishedCount) * 100, 1) : 100.0;

        $avgCallsToWin = (float) (GameWinner::where('company_id', $company->id)->avg('winning_call_sequence') ?? 0);

        // 2. Financial Metrics
        $entryFees = (int) Transaction::where('company_id', $company->id)
            ->where('type', Transaction::TYPE_ENTRY_FEE)
            ->sum('amount');

        $prizesPaid = (int) Transaction::where('company_id', $company->id)
            ->where('type', Transaction::TYPE_PRIZE)
            ->sum('amount');

        $refundsIssued = (int) Transaction::where('company_id', $company->id)
            ->where('type', Transaction::TYPE_REFUND)
            ->sum('amount');

        $deposits = (int) Transaction::where('company_id', $company->id)
            ->where('type', Transaction::TYPE_DEPOSIT)
            ->sum('amount');

        $withdrawals = (int) Transaction::where('company_id', $company->id)
            ->where('type', Transaction::TYPE_WITHDRAWAL)
            ->sum('amount');

        $netHouseEarnings = $entryFees - ($prizesPaid + $refundsIssued);
        $houseMargin = $entryFees > 0 ? round(($netHouseEarnings / $entryFees) * 100, 1) : 0.0;

        // 3. Player Metrics
        $playerQuery = User::where('company_id', $company->id)
            ->whereHas('roles', fn ($q) => $q->where('slug', Role::PLAYER));

        $totalPlayers = (clone $playerQuery)->count();
        $activePlayers = (clone $playerQuery)->where('status', 'active')->count();
        $suspendedPlayers = (clone $playerQuery)->where('status', 'suspended')->count();

        // Top 5 Winners
        $topWinners = GameWinner::where('company_id', $company->id)
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
            'players' => [
                'total' => $totalPlayers,
                'active' => $activePlayers,
                'suspended' => $suspendedPlayers,
                'top_winners' => $topWinners,
            ],
        ];
    }
}
