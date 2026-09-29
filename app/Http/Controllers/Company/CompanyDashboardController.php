<?php

namespace App\Http\Controllers\Company;

use App\Domains\Auth\Models\Role;
use App\Domains\Cards\Models\BingoCard;
use App\Domains\Financial\Models\DepositRequest;
use App\Domains\Financial\Models\Transaction;
use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameTemplate;
use App\Domains\Tenancy\Models\Company;
use App\Domains\Winners\Models\GameWinner;
use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class CompanyDashboardController extends Controller
{
    /**
     * Display the company administrator dashboard.
     */
    public function index(Company $company): Response
    {
        $entryFees = (int) Transaction::where('company_id', $company->id)->where('type', Transaction::TYPE_ENTRY_FEE)->sum('amount');
        $prizes = (int) Transaction::where('company_id', $company->id)->where('type', Transaction::TYPE_PRIZE)->sum('amount');
        $refunds = (int) Transaction::where('company_id', $company->id)->where('type', Transaction::TYPE_REFUND)->sum('amount');
        $netRevenue = $entryFees - ($prizes + $refunds);

        $completedGames = Game::where('company_id', $company->id)->where('status', Game::STATUS_COMPLETED);
        $totalPots = (int) (clone $completedGames)->sum('total_pot');
        $totalWinners = (int) (clone $completedGames)->sum('winner_payout_total');
        $totalGrossHouse = (int) (clone $completedGames)->sum('house_gross_cut');
        $totalPlatformFee = (int) (clone $completedGames)->sum('platform_fee');
        $totalNetHouse = (int) (clone $completedGames)->sum('company_net_cut');

        $pendingDeposits = DepositRequest::where('company_id', $company->id)
            ->where('type', DepositRequest::TYPE_PLAYER_DEPOSIT)
            ->where('status', DepositRequest::STATUS_PENDING)
            ->count();

        $stats = [
            'total_cards' => BingoCard::where('company_id', $company->id)->count(),
            'total_templates' => GameTemplate::forCompany($company->id)->count(),
            'active_games' => Game::where('company_id', $company->id)->whereIn('status', [
                Game::STATUS_ACTIVE,
                Game::STATUS_OPEN,
                Game::STATUS_STARTING,
                Game::STATUS_PAUSED,
            ])->count(),
            'total_players' => User::where('company_id', $company->id)
                ->whereHas('roles', fn ($q) => $q->where('slug', Role::PLAYER))
                ->count(),
            'total_games' => Game::where('company_id', $company->id)->count(),
            'credit_balance' => (int) $company->credit_balance,
            'formatted_credit_balance' => $company->formattedCreditBalance(),
            'net_revenue' => $netRevenue,
            'formatted_net_revenue' => ($netRevenue < 0 ? '-' : '+').'$'.number_format(abs($netRevenue) / 100, 2),
            'total_pots' => $totalPots,
            'formatted_total_pots' => '$'.number_format($totalPots / 100, 2),
            'total_winner_payouts' => $totalWinners,
            'formatted_winner_payouts' => '$'.number_format($totalWinners / 100, 2),
            'total_gross_house' => $totalGrossHouse,
            'formatted_gross_house' => '$'.number_format($totalGrossHouse / 100, 2),
            'total_platform_fee' => $totalPlatformFee,
            'formatted_platform_fee' => '$'.number_format($totalPlatformFee / 100, 2),
            'total_net_house' => $totalNetHouse,
            'formatted_net_house' => '$'.number_format($totalNetHouse / 100, 2),
            'pending_player_deposits_count' => $pendingDeposits,
        ];

        $recentGames = Game::where('company_id', $company->id)
            ->latest('id')
            ->limit(5)
            ->get(['id', 'game_number', 'name', 'status', 'entry_fee', 'created_at']);

        $recentWinners = GameWinner::where('company_id', $company->id)
            ->with(['user:id,name,email', 'game:id,game_number,name'])
            ->latest('id')
            ->limit(5)
            ->get();

        return Inertia::render('Company/Dashboard', [
            'stats' => $stats,
            'recent_games' => $recentGames,
            'recent_winners' => $recentWinners,
        ]);
    }
}
