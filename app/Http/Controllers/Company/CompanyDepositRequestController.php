<?php

namespace App\Http\Controllers\Company;

use App\Domains\Auth\Models\Role;
use App\Domains\Financial\Models\DepositRequest;
use App\Domains\Financial\Services\DepositRequestService;
use App\Domains\Financial\Services\LedgerService;
use App\Domains\Tenancy\Models\Company;
use App\Http\Controllers\Controller;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyDepositRequestController extends Controller
{
    public function __construct(
        protected DepositRequestService $depositRequestService,
        protected LedgerService $ledgerService
    ) {}

    /**
     * List pending and historical player deposit requests.
     */
    public function index(Company $company): Response
    {
        $pendingRequests = DepositRequest::where('company_id', $company->id)
            ->where('type', DepositRequest::TYPE_PLAYER_DEPOSIT)
            ->where('status', DepositRequest::STATUS_PENDING)
            ->with(['user:id,name,email,balance', 'paymentAccount'])
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
                'user' => $r->user ? [
                    'id' => $r->user->id,
                    'name' => $r->user->name,
                    'email' => $r->user->email,
                    'balance' => $r->user->balance,
                    'formatted_balance' => $r->user->formattedBalance(),
                ] : null,
                'payment_account' => $r->paymentAccount ? [
                    'id' => $r->paymentAccount->id,
                    'provider_name' => $r->paymentAccount->provider_name,
                    'account_name' => $r->paymentAccount->account_name,
                    'account_number' => $r->paymentAccount->account_number,
                ] : null,
                'created_at' => $r->created_at->format('M d, Y H:i'),
            ]);

        $historyRequests = DepositRequest::where('company_id', $company->id)
            ->where('type', DepositRequest::TYPE_PLAYER_DEPOSIT)
            ->whereIn('status', [DepositRequest::STATUS_APPROVED, DepositRequest::STATUS_REJECTED])
            ->with(['user:id,name,email', 'paymentAccount', 'reviewer:id,name'])
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
                'user' => $r->user ? [
                    'id' => $r->user->id,
                    'name' => $r->user->name,
                    'email' => $r->user->email,
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

        // Players for direct manual top-up modal
        $companyPlayers = User::where('company_id', $company->id)
            ->whereHas('roles', fn ($q) => $q->where('slug', Role::PLAYER))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'balance'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'balance' => $u->balance,
                'formatted_balance' => $u->formattedBalance(),
            ]);

        return Inertia::render('Company/DepositRequests/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'pending_requests' => $pendingRequests,
            'history_requests' => $historyRequests,
            'company_players' => $companyPlayers,
        ]);
    }

    /**
     * Approve a player deposit request and credit wallet balance.
     */
    public function approve(Request $request, Company $company, DepositRequest $depositRequest): RedirectResponse
    {
        if ($depositRequest->company_id !== $company->id || ! $depositRequest->isPlayerDeposit()) {
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

            return back()->with('success', "Deposit request #{$depositRequest->id} approved. Player wallet credited {$depositRequest->formattedAmount()}.");
        } catch (DomainException $e) {
            return back()->withErrors(['deposit' => $e->getMessage()]);
        }
    }

    /**
     * Reject a player deposit request.
     */
    public function reject(Request $request, Company $company, DepositRequest $depositRequest): RedirectResponse
    {
        if ($depositRequest->company_id !== $company->id || ! $depositRequest->isPlayerDeposit()) {
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

            return back()->with('success', "Deposit request #{$depositRequest->id} rejected.");
        } catch (DomainException $e) {
            return back()->withErrors(['deposit' => $e->getMessage()]);
        }
    }

    /**
     * Manual direct deposit into a player's account by company admin.
     */
    public function manualDeposit(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:1', 'max:50000'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User $player */
        $player = User::where('id', $validated['user_id'])
            ->where('company_id', $company->id)
            ->firstOrFail();

        $amountInCents = (int) round(((float) $validated['amount']) * 100);
        $reason = ! empty($validated['notes']) ? $validated['notes'] : 'Manual top-up';
        $managerId = $request->user()->isGameManager() ? $request->user()->id : null;
        $roleTitle = $request->user()->isGameManager() ? 'Manager' : 'Admin';

        $this->ledgerService->recordDeposit(
            $player,
            $amountInCents,
            referenceCode: 'MAN-'.bin2hex(random_bytes(5)),
            description: "{$roleTitle} top-up: {$reason}",
            managerId: $managerId
        );

        return back()->with('success', "Successfully credited \${$validated['amount']}.00 to {$player->name}'s account.");
    }
}
