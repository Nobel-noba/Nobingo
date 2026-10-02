<?php

namespace App\Http\Controllers\Platform;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Auth\Models\Role;
use App\Domains\Auth\Services\EmailVerificationService;
use App\Domains\Cards\Models\BingoCard;
use App\Domains\Financial\Models\DepositRequest;
use App\Domains\Financial\Models\Transaction;
use App\Domains\Games\Models\Game;
use App\Domains\Tenancy\Models\Company;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PlatformDashboardController extends Controller
{
    /**
     * Display the platform owner master dashboard.
     */
    public function index(): Response
    {
        $companies = Company::withCount(['users', 'cards'])->latest()->get();

        $totalPotsPlayed = (int) Game::withoutGlobalScopes()->where('status', Game::STATUS_COMPLETED)->sum('total_pot');
        $totalWinnerPayouts = (int) Game::withoutGlobalScopes()->where('status', Game::STATUS_COMPLETED)->sum('winner_payout_total');
        $totalHouseGross = (int) Game::withoutGlobalScopes()->where('status', Game::STATUS_COMPLETED)->sum('house_gross_cut');
        $totalPlatformRevenue = (int) Game::withoutGlobalScopes()->where('status', Game::STATUS_COMPLETED)->sum('platform_fee');
        $totalCreditsPurchased = (int) Transaction::where('type', Transaction::TYPE_CREDIT_PURCHASE)->sum('amount');
        $pendingCreditRequests = DepositRequest::forCompanyCredit()->where('status', DepositRequest::STATUS_PENDING)->count();

        $stats = [
            'total_companies' => Company::count(),
            'active_companies' => Company::where('status', 'active')->count(),
            'suspended_companies' => Company::where('status', 'suspended')->count(),
            'total_users' => User::count(),
            'total_players' => User::whereHas('roles', fn ($q) => $q->where('slug', Role::PLAYER))->count(),
            'total_games' => Game::withoutGlobalScopes()->count(),
            'active_games' => Game::withoutGlobalScopes()->where('status', Game::STATUS_ACTIVE)->count(),
            'total_platform_revenue' => $totalPlatformRevenue,
            'formatted_platform_revenue' => '$'.number_format($totalPlatformRevenue / 100, 2),
            'total_pots_played' => $totalPotsPlayed,
            'formatted_pots_played' => '$'.number_format($totalPotsPlayed / 100, 2),
            'total_winner_payouts' => $totalWinnerPayouts,
            'formatted_winner_payouts' => '$'.number_format($totalWinnerPayouts / 100, 2),
            'total_house_gross' => $totalHouseGross,
            'formatted_house_gross' => '$'.number_format($totalHouseGross / 100, 2),
            'total_credits_purchased' => $totalCreditsPurchased,
            'formatted_credits_purchased' => '$'.number_format($totalCreditsPurchased / 100, 2),
            'pending_credit_requests' => $pendingCreditRequests,
        ];

        return Inertia::render('Platform/Dashboard', [
            'stats' => $stats,
            'companies' => $companies,
        ]);
    }

    /**
     * Display the company tenant directory.
     */
    public function companies(Request $request): Response
    {
        $query = Company::withCount(['users', 'cards'])->latest();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $companies = $query->get()->map(function ($company) {
            $admin = User::where('company_id', $company->id)
                ->whereHas('roles', fn ($q) => $q->where('slug', Role::COMPANY_ADMIN))
                ->first();

            return array_merge($company->toArray(), [
                'admin_user' => $admin ? [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'email_verified_at' => $admin->email_verified_at,
                    'status' => $admin->status,
                ] : null,
            ]);
        });

        return Inertia::render('Platform/Companies', [
            'companies' => $companies,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * Provision a new company tenant along with its initial Company Admin user account.
     */
    public function storeCompany(Request $request, EmailVerificationService $emailService): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'unique:companies,slug', 'regex:/^[a-z0-9\-]+$/'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'brand_color' => ['nullable', 'string', 'max:20', 'regex:/^#[a-fA-F0-9]{3,6}$/'],
            'currency' => ['nullable', 'string', 'size:3'],
            'admin_name' => ['nullable', 'string', 'max:255'],
            'admin_email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        DB::transaction(function () use ($validated, $emailService): void {
            // 1. Create Tenant Company
            $company = Company::create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'status' => 'active',
                'settings' => [
                    'tagline' => $validated['tagline'] ?? null,
                    'brand_color' => $validated['brand_color'] ?? '#4f46e5',
                    'currency' => strtoupper($validated['currency'] ?? 'USD'),
                ],
            ]);

            // 2. Create Initial Company Administrator if provided
            if (! empty($validated['admin_email'])) {
                $adminUser = User::create([
                    'name' => $validated['admin_name'] ?? 'Company Administrator',
                    'email' => $validated['admin_email'],
                    'password' => Hash::make($validated['admin_password']),
                    'company_id' => $company->id,
                    'status' => 'active',
                    'balance' => 0,
                    'email_verified_at' => null, // Pending verification
                ]);

                // 3. Assign Role COMPANY_ADMIN
                $adminRole = Role::firstOrCreate(
                    ['slug' => Role::COMPANY_ADMIN],
                    ['name' => 'Company Admin', 'description' => 'Administrator of an individual rented company bingo installation.']
                );
                $adminUser->roles()->sync([$adminRole->id]);

                // 4. Dispatch Email Verification with OTP & Activation URL
                $emailService->sendWelcomeVerificationEmail($adminUser, $company, $validated['admin_password']);
            }
        });

        $message = ! empty($validated['admin_email'])
            ? "Company '{$validated['name']}' provisioned and activation email dispatched to {$validated['admin_email']}."
            : "Company '{$validated['name']}' provisioned successfully.";

        return redirect()->back()->with('success', $message);
    }

    /**
     * Display a specific company tenant dashboard with administrative controls.
     */
    public function showCompany(Company $company): Response
    {
        $company->loadCount(['users', 'cards']);

        $admins = User::where('company_id', $company->id)
            ->whereHas('roles', fn ($q) => $q->where('slug', Role::COMPANY_ADMIN))
            ->get();

        $completedGames = Game::withoutGlobalScopes()->where('company_id', $company->id)->where('status', Game::STATUS_COMPLETED);
        $totalPots = (int) (clone $completedGames)->sum('total_pot');
        $totalWinners = (int) (clone $completedGames)->sum('winner_payout_total');
        $totalHouseGross = (int) (clone $completedGames)->sum('house_gross_cut');
        $totalPlatformRevenue = (int) (clone $completedGames)->sum('platform_fee');

        $stats = [
            'total_cards' => BingoCard::withoutGlobalScopes()->where('company_id', $company->id)->count(),
            'available_cards' => BingoCard::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('status', BingoCard::STATUS_AVAILABLE)
                ->count(),
            'total_games' => Game::withoutGlobalScopes()->where('company_id', $company->id)->count(),
            'active_games' => Game::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('status', Game::STATUS_ACTIVE)
                ->count(),
            'total_players' => User::where('company_id', $company->id)
                ->whereHas('roles', fn ($q) => $q->where('slug', Role::PLAYER))
                ->count(),
            'total_transactions_volume' => (int) Transaction::where('company_id', $company->id)->sum('amount'),
            'credit_balance' => (int) $company->credit_balance,
            'formatted_credit_balance' => $company->formattedCreditBalance(),
            'total_pots' => $totalPots,
            'formatted_total_pots' => '$'.number_format($totalPots / 100, 2),
            'total_winner_payouts' => $totalWinners,
            'formatted_winner_payouts' => '$'.number_format($totalWinners / 100, 2),
            'total_house_gross' => $totalHouseGross,
            'formatted_house_gross' => '$'.number_format($totalHouseGross / 100, 2),
            'total_platform_revenue' => $totalPlatformRevenue,
            'formatted_platform_revenue' => '$'.number_format($totalPlatformRevenue / 100, 2),
        ];

        $recentGames = Game::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->withCount('players')
            ->latest()
            ->take(10)
            ->get();

        $auditLogs = AuditLog::where('company_id', $company->id)
            ->with('user:id,name,email')
            ->latest('id')
            ->take(15)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
                'created_at' => $log->created_at->format('M d, Y H:i:s'),
            ]);

        return Inertia::render('Platform/CompanyShow', [
            'company' => $company,
            'admins' => $admins,
            'stats' => $stats,
            'recent_games' => $recentGames,
            'audit_logs' => $auditLogs,
        ]);
    }

    /**
     * Directly allocate / top up platform credits for a rented company tenant.
     */
    public function topupCompanyCredits(Company $company, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $amountInCents = (int) round(((float) $validated['amount']) * 100);

        $balanceBefore = (int) $company->credit_balance;
        $balanceAfter = $balanceBefore + $amountInCents;
        $company->update(['credit_balance' => $balanceAfter]);

        Transaction::create([
            'company_id' => $company->id,
            'user_id' => null,
            'type' => Transaction::TYPE_CREDIT_PURCHASE,
            'amount' => $amountInCents,
            'currency' => 'USD',
            'status' => Transaction::STATUS_COMPLETED,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reference_code' => 'TOPUP-'.strtoupper(bin2hex(random_bytes(4))),
            'description' => 'Platform Owner Direct Credit Top-up: '.($validated['notes'] ?? 'Manual credit allocation'),
        ]);

        AuditLog::create([
            'company_id' => $company->id,
            'user_id' => $request->user()?->id,
            'action' => AuditLog::ACTION_BALANCE_ADJUSTED,
            'description' => "Platform Owner directly credited {$company->name} balance with \${$validated['amount']}.00. Notes: ".($validated['notes'] ?? 'None'),
            'details' => [
                'amount' => $amountInCents,
                'notes' => $validated['notes'] ?? null,
            ],
            'created_at' => now(),
        ]);

        return redirect()->back()->with('success', "Successfully allocated \${$validated['amount']}.00 platform credits to {$company->name}.");
    }

    /**
     * Update tenant company settings and branding.
     */
    public function updateCompanySettings(Company $company, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'brand_color' => ['nullable', 'string', 'max:20', 'regex:/^#[a-fA-F0-9]{3,6}$/'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        $settings = array_merge($company->settings ?? [], [
            'tagline' => $validated['tagline'] ?? null,
            'brand_color' => $validated['brand_color'] ?? '#4f46e5',
            'currency' => strtoupper($validated['currency'] ?? 'USD'),
        ]);

        $company->update([
            'name' => $validated['name'],
            'domain' => $validated['domain'] ?? null,
            'settings' => $settings,
        ]);

        return redirect()->back()->with('success', 'Company settings updated successfully.');
    }

    /**
     * Revoke or restore company tenant access (toggle active <-> suspended).
     */
    public function toggleCompanyStatus(Company $company): RedirectResponse
    {
        $newStatus = $company->status === 'active' ? 'suspended' : 'active';
        $company->update(['status' => $newStatus]);

        $action = $newStatus === 'active' ? 'restored' : 'revoked/suspended';

        return redirect()->back()->with('success', "Access for company '{$company->name}' has been {$action}.");
    }

    /**
     * Directly update a company admin user's password (by Platform Owner).
     */
    public function directChangeAdminPassword(Company $company, User $user, Request $request): RedirectResponse
    {
        if ($user->company_id !== $company->id) {
            abort(403, 'User does not belong to this company.');
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->back()->with('success', "Password for {$user->name} ({$user->email}) updated successfully.");
    }

    /**
     * Request an email-verified password reset for a company admin.
     */
    public function requestAdminPasswordReset(Company $company, User $user, EmailVerificationService $emailService): RedirectResponse
    {
        if ($user->company_id !== $company->id) {
            abort(403, 'User does not belong to this company.');
        }

        $emailService->sendPasswordResetOtpEmail($user, $company);

        return redirect()->back()->with('success', "A 6-digit security verification code has been dispatched to {$user->email}.");
    }

    /**
     * Confirm password reset using the 6-digit OTP code sent via email.
     */
    public function confirmAdminPasswordReset(Company $company, User $user, Request $request, EmailVerificationService $emailService): RedirectResponse
    {
        if ($user->company_id !== $company->id) {
            abort(403, 'User does not belong to this company.');
        }

        $validated = $request->validate([
            'otp' => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $isValid = $emailService->verifyOtp($user, $validated['otp'], EmailVerificationService::ACTION_PASSWORD_RESET);

        if (! $isValid) {
            throw ValidationException::withMessages([
                'otp' => 'The provided 6-digit verification code is invalid or has expired.',
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);

        return redirect()->back()->with('success', "Password successfully reset for {$user->name} via email verification.");
    }

    /**
     * Verify email address via signed URL token.
     */
    public function verifyEmail(Request $request, User $user, EmailVerificationService $emailService): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('login')->with('error', 'Invalid or expired email verification link.');
        }

        $token = $request->query('token');
        $action = $request->query('action', EmailVerificationService::ACTION_WELCOME_VERIFICATION);

        if ($token && $emailService->verifyToken($user, (string) $token, (string) $action)) {
            $emailService->markEmailAsVerified($user);

            return redirect()->route('login')->with('success', 'Email successfully verified! You may now log in to your company console.');
        }

        return redirect()->route('login')->with('error', 'Verification token invalid or already redeemed.');
    }
}
