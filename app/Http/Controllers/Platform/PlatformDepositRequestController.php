<?php

namespace App\Http\Controllers\Platform;

use App\Domains\Financial\Models\DepositRequest;
use App\Domains\Financial\Services\DepositRequestService;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformDepositRequestController extends Controller
{
    public function __construct(
        protected DepositRequestService $depositRequestService
    ) {}

    /**
     * List pending and historical company platform credit purchase requests.
     */
    public function index(): Response
    {
        $pendingRequests = DepositRequest::forCompanyCredit()
            ->where('status', DepositRequest::STATUS_PENDING)
            ->with(['company', 'paymentAccount'])
            ->latest('id')
            ->get()
            ->map(fn (DepositRequest $r) => [
                'id' => $r->id,
                'amount' => $r->amount,
                'formatted_amount' => $r->formattedAmount(),
                'status' => $r->status,
                'reference_number' => $r->reference_number,
                'receipt_url' => $r->receiptUrl(),
                'notes' => $r->notes,
                'company' => $r->company ? [
                    'id' => $r->company->id,
                    'name' => $r->company->name,
                    'slug' => $r->company->slug,
                    'credit_balance' => (int) $r->company->credit_balance,
                    'formatted_credit_balance' => $r->company->formattedCreditBalance(),
                ] : null,
                'payment_account' => $r->paymentAccount ? [
                    'id' => $r->paymentAccount->id,
                    'provider_name' => $r->paymentAccount->provider_name,
                    'account_name' => $r->paymentAccount->account_name,
                    'account_number' => $r->paymentAccount->account_number,
                ] : null,
                'created_at' => $r->created_at->format('M d, Y H:i'),
            ]);

        $historyRequests = DepositRequest::forCompanyCredit()
            ->whereIn('status', [DepositRequest::STATUS_APPROVED, DepositRequest::STATUS_REJECTED])
            ->with(['company', 'paymentAccount', 'reviewer:id,name'])
            ->latest('id')
            ->paginate(15)
            ->through(fn (DepositRequest $r) => [
                'id' => $r->id,
                'amount' => $r->amount,
                'formatted_amount' => $r->formattedAmount(),
                'status' => $r->status,
                'reference_number' => $r->reference_number,
                'receipt_url' => $r->receiptUrl(),
                'notes' => $r->notes,
                'reviewer_notes' => $r->reviewer_notes,
                'company' => $r->company ? [
                    'id' => $r->company->id,
                    'name' => $r->company->name,
                    'slug' => $r->company->slug,
                ] : null,
                'payment_account' => $r->paymentAccount ? [
                    'id' => $r->paymentAccount->id,
                    'provider_name' => $r->paymentAccount->provider_name,
                ] : null,
                'reviewer' => $r->reviewer ? [
                    'id' => $r->reviewer->id,
                    'name' => $r->reviewer->name,
                ] : null,
                'created_at' => $r->created_at->format('M d, Y H:i'),
                'reviewed_at' => $r->reviewed_at?->format('M d, Y H:i'),
            ]);

        return Inertia::render('Platform/DepositRequests/Index', [
            'pending_requests' => $pendingRequests,
            'history_requests' => $historyRequests,
        ]);
    }

    /**
     * Approve a company platform credit purchase request and credit their balance.
     */
    public function approve(Request $request, DepositRequest $depositRequest): RedirectResponse
    {
        if (! $depositRequest->isCompanyCredit()) {
            abort(404);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->depositRequestService->approve(
                $depositRequest,
                $request->user(),
                $validated['notes'] ?? null
            );

            return back()->with('success', "Credit request #{$depositRequest->id} approved. {$depositRequest->company->name} was credited {$depositRequest->formattedAmount()}.");
        } catch (DomainException $e) {
            return back()->withErrors(['deposit' => $e->getMessage()]);
        }
    }

    /**
     * Reject a company platform credit purchase request.
     */
    public function reject(Request $request, DepositRequest $depositRequest): RedirectResponse
    {
        if (! $depositRequest->isCompanyCredit()) {
            abort(404);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->depositRequestService->reject(
                $depositRequest,
                $request->user(),
                $validated['reason']
            );

            return back()->with('success', "Credit request #{$depositRequest->id} rejected.");
        } catch (DomainException $e) {
            return back()->withErrors(['deposit' => $e->getMessage()]);
        }
    }
}
