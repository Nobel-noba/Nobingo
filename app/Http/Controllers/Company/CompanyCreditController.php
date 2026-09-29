<?php

namespace App\Http\Controllers\Company;

use App\Domains\Financial\Models\DepositRequest;
use App\Domains\Financial\Models\PaymentAccount;
use App\Domains\Financial\Services\DepositRequestService;
use App\Domains\Tenancy\Models\Company;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyCreditController extends Controller
{
    public function __construct(
        protected DepositRequestService $depositRequestService
    ) {}

    /**
     * View company platform credit balance and purchase credit history.
     */
    public function index(Company $company): Response
    {
        $platformAccounts = PaymentAccount::forPlatform()
            ->active()
            ->get();

        $creditRequests = DepositRequest::where('company_id', $company->id)
            ->where('type', DepositRequest::TYPE_COMPANY_CREDIT)
            ->with(['paymentAccount', 'reviewer:id,name,email'])
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
                'reviewer' => $r->reviewer ? [
                    'id' => $r->reviewer->id,
                    'name' => $r->reviewer->name,
                ] : null,
                'created_at' => $r->created_at->format('M d, Y H:i'),
                'reviewed_at' => $r->reviewed_at?->format('M d, Y H:i'),
            ]);

        return Inertia::render('Company/Credits/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'credit_balance' => (int) $company->credit_balance,
                'formatted_credit_balance' => $company->formattedCreditBalance(),
            ],
            'platform_accounts' => $platformAccounts,
            'credit_requests' => $creditRequests,
        ]);
    }

    /**
     * Submit a credit purchase request with payment proof receipt.
     */
    public function store(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'payment_account_id' => ['nullable', 'exists:payment_accounts,id'],
            'amount' => ['required', 'numeric', 'min:5', 'max:50000'], // Minimum $5.00
            'reference_number' => ['nullable', 'string', 'max:100'],
            'receipt' => ['required', 'file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:5120'], // 5MB max
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $paymentAccount = null;
        if (! empty($validated['payment_account_id'])) {
            $paymentAccount = PaymentAccount::forPlatform()->find($validated['payment_account_id']);
        }

        $receiptPath = $request->file('receipt')->store('receipts', 'public');
        $amountInCents = (int) round(((float) $validated['amount']) * 100);

        $this->depositRequestService->createCompanyCreditRequest(
            company: $company,
            amount: $amountInCents,
            paymentAccount: $paymentAccount,
            receiptPath: $receiptPath,
            referenceNumber: $validated['reference_number'] ?? null,
            notes: $validated['notes'] ?? null,
        );

        return back()->with('success', 'Credit purchase request submitted! The platform owner will verify your receipt and credit your balance.');
    }
}
