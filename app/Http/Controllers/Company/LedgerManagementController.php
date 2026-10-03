<?php

namespace App\Http\Controllers\Company;

use App\Domains\Auth\Models\Role;
use App\Domains\Financial\Models\Transaction;
use App\Domains\Tenancy\Models\Company;
use App\Domains\Winners\Models\GameWinner;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class LedgerManagementController extends Controller
{
    /**
     * Display the company treasury & financial transaction ledger.
     */
    public function index(Request $request, Company $company): Response
    {
        $user = $request->user();
        $isGameManager = $user && $user->isGameManager() && ! $user->isCompanyAdmin() && ! $user->isPlatformOwner();

        $managerId = $isGameManager ? $user->id : ($request->filled('manager_id') ? (int) $request->query('manager_id') : null);
        $type = $request->query('type');
        $search = $request->query('search');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = Transaction::with(['user:id,name,email', 'gameManager:id,name,email', 'reference'])
            ->where('company_id', $company->id)
            ->latest('id');

        if ($managerId !== null) {
            $query->where('game_manager_id', $managerId);
        }

        if ($startDate) {
            $query->where('created_at', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate) {
            $query->where('created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        if ($type && $type !== 'ALL') {
            $query->where('type', $type);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $transactions = $query->paginate(20)->withQueryString()->through(fn (Transaction $tx) => [
            'id' => $tx->id,
            'type' => $tx->type,
            'amount' => $tx->amount,
            'formatted_amount' => $tx->formattedAmount(),
            'currency' => $tx->currency,
            'status' => $tx->status,
            'balance_before' => $tx->balance_before,
            'formatted_balance_before' => $tx->formattedBalanceBefore(),
            'balance_after' => $tx->balance_after,
            'formatted_balance_after' => $tx->formattedBalanceAfter(),
            'reference_type' => class_basename($tx->reference_type ?? ''),
            'reference_id' => $tx->reference_id,
            'reference_code' => $tx->reference_code,
            'description' => $tx->description,
            'is_credit' => $tx->isCredit(),
            'is_debit' => $tx->isDebit(),
            'is_walkin' => $tx->user_id === null && ($tx->type === Transaction::TYPE_ENTRY_FEE || $tx->type === Transaction::TYPE_PRIZE),
            'user' => $tx->user ? [
                'id' => $tx->user->id,
                'name' => $tx->user->name,
                'email' => $tx->user->email,
            ] : null,
            'game_manager' => $tx->gameManager ? [
                'id' => $tx->gameManager->id,
                'name' => $tx->gameManager->name,
                'email' => $tx->gameManager->email,
            ] : null,
            'created_at' => $tx->created_at->format('M d, Y H:i:s'),
        ]);

        // Compute scoped statistics
        $statsQuery = Transaction::where('company_id', $company->id);
        if ($managerId !== null) {
            $statsQuery->where('game_manager_id', $managerId);
        }
        if ($startDate) {
            $statsQuery->where('created_at', '>=', Carbon::parse($startDate)->startOfDay());
        }
        if ($endDate) {
            $statsQuery->where('created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        $entryFees = (int) (clone $statsQuery)->where('type', Transaction::TYPE_ENTRY_FEE)->sum('amount');
        $prizes = (int) (clone $statsQuery)->where('type', Transaction::TYPE_PRIZE)->sum('amount');
        $refunds = (int) (clone $statsQuery)->where('type', Transaction::TYPE_REFUND)->sum('amount');
        $deposits = (int) (clone $statsQuery)->where('type', Transaction::TYPE_DEPOSIT)->sum('amount');
        $withdrawals = (int) (clone $statsQuery)->where('type', Transaction::TYPE_WITHDRAWAL)->sum('amount');

        // Walk-in specific metrics
        $walkInSales = (int) (clone $statsQuery)->where('type', Transaction::TYPE_ENTRY_FEE)->whereNull('user_id')->sum('amount');
        $walkInPrizes = (int) (clone $statsQuery)->where('type', Transaction::TYPE_PRIZE)->whereNull('user_id')->sum('amount');
        $walkInNetCash = $walkInSales - $walkInPrizes;

        // Cash on Hand calculation (Physical Cash In minus Physical Cash Out)
        $cashInflows = $walkInSales + $deposits;
        $cashOutflows = $walkInPrizes + $withdrawals;
        $periodNetCash = $cashInflows - $cashOutflows;

        $pendingPayouts = GameWinner::where('company_id', $company->id)
            ->where('payout_status', GameWinner::PAYOUT_STATUS_PENDING);
        if ($managerId !== null) {
            $pendingPayouts->whereHas('game', fn ($g) => $g->where('created_by', $managerId));
        }
        $pendingPayoutsSum = (int) $pendingPayouts->sum('payout_amount');

        $netHouseEarnings = $entryFees - ($prizes + $refunds);

        $managers = [];
        if (! $isGameManager) {
            $managers = User::where('company_id', $company->id)
                ->whereHas('roles', fn ($q) => $q->where('slug', Role::GAME_MANAGER))
                ->orderBy('name')
                ->select(['id', 'name', 'email'])
                ->get();
        }

        return Inertia::render('Company/Ledger/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'is_game_manager' => $isGameManager,
            'managers' => $managers,
            'transactions' => $transactions,
            'statistics' => [
                'entry_fees' => $entryFees,
                'formatted_entry_fees' => '$'.number_format($entryFees / 100, 2),
                'prizes' => $prizes,
                'formatted_prizes' => '$'.number_format($prizes / 100, 2),
                'refunds' => $refunds,
                'formatted_refunds' => '$'.number_format($refunds / 100, 2),
                'deposits' => $deposits,
                'formatted_deposits' => '$'.number_format($deposits / 100, 2),
                'withdrawals' => $withdrawals,
                'formatted_withdrawals' => '$'.number_format($withdrawals / 100, 2),
                'walkin_sales' => $walkInSales,
                'formatted_walkin_sales' => '$'.number_format($walkInSales / 100, 2),
                'walkin_prizes' => $walkInPrizes,
                'formatted_walkin_prizes' => '$'.number_format($walkInPrizes / 100, 2),
                'walkin_net_cash' => $walkInNetCash,
                'formatted_walkin_net_cash' => ($walkInNetCash < 0 ? '-' : '+').'$'.number_format(abs($walkInNetCash) / 100, 2),
                'period_net_cash' => $periodNetCash,
                'formatted_period_net_cash' => ($periodNetCash < 0 ? '-' : '+').'$'.number_format(abs($periodNetCash) / 100, 2),
                'pending_payouts' => $pendingPayoutsSum,
                'formatted_pending_payouts' => '$'.number_format($pendingPayoutsSum / 100, 2),
                'net_house_earnings' => $netHouseEarnings,
                'formatted_net_house_earnings' => ($netHouseEarnings < 0 ? '-' : '+').'$'.number_format(abs($netHouseEarnings) / 100, 2),
            ],
            'filters' => [
                'type' => $type ?? 'ALL',
                'search' => $search ?? '',
                'manager_id' => $managerId ? (string) $managerId : '',
                'start_date' => $startDate ?? '',
                'end_date' => $endDate ?? '',
            ],
        ]);
    }
}
