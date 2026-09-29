<?php

namespace App\Http\Controllers\Company;

use App\Domains\Financial\Models\Transaction;
use App\Domains\Tenancy\Models\Company;
use App\Domains\Winners\Models\GameWinner;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LedgerManagementController extends Controller
{
    /**
     * Display the company treasury & financial transaction ledger.
     */
    public function index(Request $request, Company $company): Response
    {
        $type = $request->query('type');
        $search = $request->query('search');

        $query = Transaction::with(['user:id,name,email', 'reference'])
            ->where('company_id', $company->id)
            ->latest('id');

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
            'user' => $tx->user ? [
                'id' => $tx->user->id,
                'name' => $tx->user->name,
                'email' => $tx->user->email,
            ] : null,
            'created_at' => $tx->created_at->format('M d, Y H:i:s'),
        ]);

        // Compute company statistics
        $entryFees = Transaction::where('company_id', $company->id)
            ->where('type', Transaction::TYPE_ENTRY_FEE)
            ->sum('amount');

        $prizes = Transaction::where('company_id', $company->id)
            ->where('type', Transaction::TYPE_PRIZE)
            ->sum('amount');

        $refunds = Transaction::where('company_id', $company->id)
            ->where('type', Transaction::TYPE_REFUND)
            ->sum('amount');

        $deposits = Transaction::where('company_id', $company->id)
            ->where('type', Transaction::TYPE_DEPOSIT)
            ->sum('amount');

        $withdrawals = Transaction::where('company_id', $company->id)
            ->where('type', Transaction::TYPE_WITHDRAWAL)
            ->sum('amount');

        $pendingPayouts = GameWinner::where('company_id', $company->id)
            ->where('payout_status', GameWinner::PAYOUT_STATUS_PENDING)
            ->sum('payout_amount');

        $netHouseEarnings = $entryFees - ($prizes + $refunds);

        return Inertia::render('Company/Ledger/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
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
                'pending_payouts' => $pendingPayouts,
                'formatted_pending_payouts' => '$'.number_format($pendingPayouts / 100, 2),
                'net_house_earnings' => $netHouseEarnings,
                'formatted_net_house_earnings' => ($netHouseEarnings < 0 ? '-' : '+').'$'.number_format(abs($netHouseEarnings) / 100, 2),
            ],
            'filters' => [
                'type' => $type ?? 'ALL',
                'search' => $search ?? '',
            ],
        ]);
    }
}
