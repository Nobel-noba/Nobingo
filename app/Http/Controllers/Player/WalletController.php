<?php

namespace App\Http\Controllers\Player;

use App\Domains\Financial\Contracts\PaymentGatewayInterface;
use App\Domains\Financial\Models\DepositRequest;
use App\Domains\Financial\Models\PaymentAccount;
use App\Domains\Financial\Models\Transaction;
use App\Domains\Financial\Services\DepositRequestService;
use App\Domains\Tenancy\Models\Company;
use App\Http\Controllers\Controller;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class WalletController extends Controller
{
    public function __construct(
        protected PaymentGatewayInterface $paymentGateway,
        protected DepositRequestService $depositRequestService
    ) {}

    /**
     * Display the player's personal wallet and transaction history.
     */
    public function index(Request $request, Company $company): Response
    {
        /** @var User $user */
        $user = $request->user();

        $transactions = Transaction::where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->latest('id')
            ->paginate(15)
            ->through(fn (Transaction $tx) => [
                'id' => $tx->id,
                'type' => $tx->type,
                'amount' => $tx->amount,
                'formatted_amount' => $tx->formattedAmount(),
                'status' => $tx->status,
                'balance_before' => $tx->balance_before,
                'formatted_balance_before' => $tx->formattedBalanceBefore(),
                'balance_after' => $tx->balance_after,
                'formatted_balance_after' => $tx->formattedBalanceAfter(),
                'reference_code' => $tx->reference_code,
                'description' => $tx->description,
                'is_credit' => $tx->isCredit(),
                'is_debit' => $tx->isDebit(),
                'created_at' => $tx->created_at->format('M d, Y H:i:s'),
            ]);

        // User statistics
        $totalDeposited = Transaction::where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('type', Transaction::TYPE_DEPOSIT)
            ->sum('amount');

        $totalWithdrawn = Transaction::where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('type', Transaction::TYPE_WITHDRAWAL)
            ->sum('amount');

        $totalWon = Transaction::where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('type', Transaction::TYPE_PRIZE)
            ->sum('amount');

        $totalEntryFees = Transaction::where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('type', Transaction::TYPE_ENTRY_FEE)
            ->sum('amount');

        $companyPaymentAccounts = PaymentAccount::where('company_id', $company->id)
            ->active()
            ->get();

        $depositRequests = DepositRequest::where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('type', DepositRequest::TYPE_PLAYER_DEPOSIT)
            ->with(['paymentAccount', 'reviewer:id,name'])
            ->latest('id')
            ->paginate(10)
            ->through(fn (DepositRequest $r) => [
                'id' => $r->id,
                'amount' => $r->amount,
                'formatted_amount' => $r->formattedAmount(),
                'status' => $r->status,
                'reference_number' => $r->reference_number,
                'receipt_url' => $r->receiptUrl(),
                'notes' => $r->notes,
                'reviewer_notes' => $r->reviewer_notes,
                'payment_account' => $r->paymentAccount ? [
                    'id' => $r->paymentAccount->id,
                    'provider_name' => $r->paymentAccount->provider_name,
                    'account_name' => $r->paymentAccount->account_name,
                    'account_number' => $r->paymentAccount->account_number,
                ] : null,
                'created_at' => $r->created_at->format('M d, Y H:i'),
                'reviewed_at' => $r->reviewed_at?->format('M d, Y H:i'),
            ]);

        return Inertia::render('Player/Wallet', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'balance' => $user->balance,
            'formatted_balance' => $user->formattedBalance(),
            'transactions' => $transactions,
            'company_payment_accounts' => $companyPaymentAccounts,
            'deposit_requests' => $depositRequests,
            'statistics' => [
                'total_deposited' => $totalDeposited,
                'formatted_total_deposited' => '$'.number_format($totalDeposited / 100, 2),
                'total_withdrawn' => $totalWithdrawn,
                'formatted_total_withdrawn' => '$'.number_format($totalWithdrawn / 100, 2),
                'total_won' => $totalWon,
                'formatted_total_won' => '$'.number_format($totalWon / 100, 2),
                'total_entry_fees' => $totalEntryFees,
                'formatted_total_entry_fees' => '$'.number_format($totalEntryFees / 100, 2),
            ],
        ]);
    }

    /**
     * Submit a player deposit request with payment receipt for admin approval.
     */
    public function storeDepositRequest(Request $request, Company $company): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'payment_account_id' => ['required', 'exists:payment_accounts,id'],
            'amount' => ['required', 'numeric', 'min:1', 'max:50000'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'receipt' => ['required', 'file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $paymentAccount = PaymentAccount::where('id', $validated['payment_account_id'])
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->firstOrFail();

        $receiptPath = $request->file('receipt')->store('receipts', 'public');
        $amountInCents = (int) round(((float) $validated['amount']) * 100);

        $this->depositRequestService->createPlayerDepositRequest(
            user: $user,
            amount: $amountInCents,
            paymentAccount: $paymentAccount,
            receiptPath: $receiptPath,
            referenceNumber: $validated['reference_number'] ?? null,
            notes: $validated['notes'] ?? null,
        );

        return back()->with('success', 'Deposit request submitted successfully! Your balance will be credited once the company admin reviews and approves your receipt.');
    }

    /**
     * Direct player self-deposit is disabled.
     */
    public function deposit(Request $request, Company $company): RedirectResponse
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:50000'],
        ]);

        return back()->withErrors([
            'deposit' => 'Direct self-deposits are disabled. Please submit a deposit request with your payment receipt for admin approval.',
        ]);
    }

    /**
     * Process a simulated wallet withdrawal in sandbox mode.
     */
    public function withdraw(Request $request, Company $company): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->isActive()) {
            return back()->withErrors(['withdraw' => 'Your account is suspended. Withdrawals are disabled.']);
        }

        $maxDollars = (int) floor($user->balance / 100);

        if ($maxDollars < 1) {
            return back()->withErrors(['withdraw' => 'Insufficient funds available for withdrawal (minimum $1.00).']);
        }

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1', "max:{$maxDollars}"],
        ]);

        $amountInCents = (int) ($validated['amount'] * 100);

        try {
            $this->paymentGateway->withdraw($user, $amountInCents, [
                'description' => "Sandbox Withdrawal of \${$validated['amount']}.00",
            ]);

            return back()->with('success', "Withdrawal of \${$validated['amount']}.00 completed successfully!");
        } catch (DomainException|RuntimeException $e) {
            return back()->withErrors(['withdraw' => $e->getMessage()]);
        }
    }
}
