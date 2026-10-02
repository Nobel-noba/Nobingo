<?php

use App\Domains\Tenancy\Models\Company;
use App\Http\Controllers\Company\AuditLogController;
use App\Http\Controllers\Company\CardManagementController;
use App\Http\Controllers\Company\CompanyCreditController;
use App\Http\Controllers\Company\CompanyDashboardController;
use App\Http\Controllers\Company\CompanyDepositRequestController;
use App\Http\Controllers\Company\CompanyPaymentAccountController;
use App\Http\Controllers\Company\GameAuditController;
use App\Http\Controllers\Company\GameManagementController;
use App\Http\Controllers\Company\GameManagerController;
use App\Http\Controllers\Company\LedgerManagementController;
use App\Http\Controllers\Company\PatternManagementController;
use App\Http\Controllers\Company\PlayerManagementController;
use App\Http\Controllers\Company\ReportController;
use App\Http\Controllers\Company\TemplateManagementController;
use App\Http\Controllers\Company\WinnerManagementController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Platform\EmailSettingsController;
use App\Http\Controllers\Platform\PlatformDashboardController;
use App\Http\Controllers\Platform\PlatformDepositRequestController;
use App\Http\Controllers\Platform\PlatformPaymentAccountController;
use App\Http\Controllers\Platform\PlatformRevenueSettingsController;
use App\Http\Controllers\Player\BingoClaimController;
use App\Http\Controllers\Player\GameLobbyController;
use App\Http\Controllers\Player\PlayerDashboardController;
use App\Http\Controllers\Player\WalletController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Production Diagnostic & Liveness Health Probe
Route::get('/health', HealthController::class)->name('health');

Route::get('/', function () {
    $companies = Company::where('status', 'active')->get();

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
        'companies' => $companies,
    ]);
});

// Smart Role-Based Dashboard Dispatcher
Route::get('/dashboard', function (Request $request) {
    $user = $request->user();

    if ($user->isPlatformOwner()) {
        return redirect()->route('platform.dashboard');
    }

    if ($user->company) {
        if ($user->isCompanyAdmin() || $user->isGameManager()) {
            return redirect()->route('company.admin.dashboard', ['company' => $user->company->slug]);
        }

        return redirect()->route('player.dashboard', ['company' => $user->company->slug]);
    }

    return Inertia::render('Dashboard');
})->middleware(['auth'])->name('dashboard');

// Platform Owner Protected Routes
Route::middleware(['auth', 'role:PLATFORM_OWNER'])
    ->prefix('platform')
    ->name('platform.')
    ->group(function () {
        Route::get('/dashboard', [PlatformDashboardController::class, 'index'])->name('dashboard');
        Route::get('/companies', [PlatformDashboardController::class, 'companies'])->name('companies');
        Route::post('/companies', [PlatformDashboardController::class, 'storeCompany'])->name('companies.store');
        Route::get('/companies/{company}', [PlatformDashboardController::class, 'showCompany'])->name('companies.show');
        Route::patch('/companies/{company}/settings', [PlatformDashboardController::class, 'updateCompanySettings'])->name('companies.settings');
        Route::patch('/companies/{company}/status', [PlatformDashboardController::class, 'toggleCompanyStatus'])->name('companies.status');
        Route::post('/companies/{company}/topup-credits', [PlatformDashboardController::class, 'topupCompanyCredits'])->name('companies.topup-credits');
        Route::post('/companies/{company}/users/{user}/password-direct', [PlatformDashboardController::class, 'directChangeAdminPassword'])->name('companies.users.password-direct');
        Route::post('/companies/{company}/users/{user}/request-password-reset', [PlatformDashboardController::class, 'requestAdminPasswordReset'])->name('companies.users.request-password-reset');
        Route::post('/companies/{company}/users/{user}/confirm-password-reset', [PlatformDashboardController::class, 'confirmAdminPasswordReset'])->name('companies.users.confirm-password-reset');

        // Email Service Integration Console
        Route::get('/settings/email', [EmailSettingsController::class, 'index'])->name('settings.email');
        Route::post('/settings/email', [EmailSettingsController::class, 'update'])->name('settings.email.update');
        Route::post('/settings/email/test', [EmailSettingsController::class, 'sendTest'])->name('settings.email.test');

        // Deposit Requests & Credit Approvals
        Route::get('/deposit-requests', [PlatformDepositRequestController::class, 'index'])->name('deposit-requests.index');
        Route::post('/deposit-requests/{depositRequest}/approve', [PlatformDepositRequestController::class, 'approve'])->name('deposit-requests.approve');
        Route::post('/deposit-requests/{depositRequest}/reject', [PlatformDepositRequestController::class, 'reject'])->name('deposit-requests.reject');

        // Platform Payment Accounts
        Route::get('/payment-accounts', [PlatformPaymentAccountController::class, 'index'])->name('payment-accounts.index');
        Route::post('/payment-accounts', [PlatformPaymentAccountController::class, 'store'])->name('payment-accounts.store');
        Route::put('/payment-accounts/{paymentAccount}', [PlatformPaymentAccountController::class, 'update'])->name('payment-accounts.update');
        Route::delete('/payment-accounts/{paymentAccount}', [PlatformPaymentAccountController::class, 'destroy'])->name('payment-accounts.destroy');

        // Revenue Sharing Settings
        Route::get('/settings/revenue', [PlatformRevenueSettingsController::class, 'index'])->name('settings.revenue');
        Route::post('/settings/revenue', [PlatformRevenueSettingsController::class, 'update'])->name('settings.revenue.update');
    });

// Public Signed Email Verification Route
Route::get('/platform/verify-email/{user}', [PlatformDashboardController::class, 'verifyEmail'])
    ->name('platform.verify-email');

// Company Shared Administration Routes (Company Admin, Game Manager, Platform Owner)
Route::middleware(['auth', 'tenant', 'role:PLATFORM_OWNER,COMPANY_ADMIN,GAME_MANAGER'])
    ->prefix('c/{company:slug}/admin')
    ->name('company.admin.')
    ->group(function () {
        Route::get('/', [CompanyDashboardController::class, 'index'])->name('dashboard');

        // Bingo Games (Phase 4 & 5)
        Route::get('/games', [GameManagementController::class, 'index'])->name('games.index');
        Route::get('/games/create', [GameManagementController::class, 'create'])->name('games.create');
        Route::post('/games', [GameManagementController::class, 'store'])->name('games.store');
        Route::get('/games/{game}', [GameManagementController::class, 'show'])->name('games.show');
        Route::patch('/games/{game}/status', [GameManagementController::class, 'updateStatus'])->name('games.update-status');
        Route::post('/games/{game}/call-next', [GameManagementController::class, 'callNext'])->name('games.call-next');
        Route::post('/games/{game}/assign-card', [GameManagementController::class, 'assignCard'])->name('games.assign-card');
        Route::post('/games/{game}/claims/{winner}/confirm', [GameManagementController::class, 'confirmClaim'])->name('games.claims.confirm');
        Route::post('/games/{game}/claims/{winner}/reject', [GameManagementController::class, 'rejectClaim'])->name('games.claims.reject');
        Route::post('/games/{game}/verify-card', [GameManagementController::class, 'verifyCard'])->name('games.verify-card');
        Route::post('/games/{game}/declare-walkin-winner', [GameManagementController::class, 'declareWalkInWinner'])->name('games.declare-walkin-winner');
        Route::get('/games/{game}/audit', [GameAuditController::class, 'show'])->name('games.audit');

        // Player Directory & Counter Operations
        Route::get('/players', [PlayerManagementController::class, 'index'])->name('players.index');
        Route::post('/players', [PlayerManagementController::class, 'store'])->name('players.store');
        Route::get('/players/{player}', [PlayerManagementController::class, 'show'])->name('players.show');
        Route::patch('/players/{player}/toggle-status', [PlayerManagementController::class, 'toggleStatus'])->name('players.toggle-status');
        Route::post('/players/{player}/adjust-balance', [PlayerManagementController::class, 'adjustBalance'])->name('players.adjust-balance');
        Route::post('/players/{player}/reset-password', [PlayerManagementController::class, 'resetPassword'])->name('players.reset-password');

        // Player Deposit Requests & Manual Cashier
        Route::get('/deposit-requests', [CompanyDepositRequestController::class, 'index'])->name('deposit-requests.index');
        Route::post('/deposit-requests/{depositRequest}/approve', [CompanyDepositRequestController::class, 'approve'])->name('deposit-requests.approve');
        Route::post('/deposit-requests/{depositRequest}/reject', [CompanyDepositRequestController::class, 'reject'])->name('deposit-requests.reject');
        Route::post('/players/manual-deposit', [CompanyDepositRequestController::class, 'manualDeposit'])->name('players.manual-deposit');

        // Winners Ledger
        Route::get('/winners', [WinnerManagementController::class, 'index'])->name('winners.index');

        // Tenant Audit Logs (Personal logs for Game Manager, all logs for Admin)
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });

// Company Executive Administration Routes (Restricted to Company Admin & Platform Owner)
Route::middleware(['auth', 'tenant', 'role:PLATFORM_OWNER,COMPANY_ADMIN'])
    ->prefix('c/{company:slug}/admin')
    ->name('company.admin.')
    ->group(function () {
        // Game Managers Management
        Route::get('/game-managers', [GameManagerController::class, 'index'])->name('game-managers.index');
        Route::post('/game-managers', [GameManagerController::class, 'store'])->name('game-managers.store');
        Route::patch('/game-managers/{gameManager}', [GameManagerController::class, 'update'])->name('game-managers.update');
        Route::post('/game-managers/{gameManager}/reset-password', [GameManagerController::class, 'resetPassword'])->name('game-managers.reset-password');
        Route::delete('/game-managers/{gameManager}', [GameManagerController::class, 'destroy'])->name('game-managers.destroy');

        // Fixed Card Inventory (Phase 2)
        Route::get('/cards', [CardManagementController::class, 'index'])->name('cards.index');
        Route::get('/cards/{card}', [CardManagementController::class, 'show'])->name('cards.show');
        Route::post('/cards/generate-batch', [CardManagementController::class, 'generateBatch'])->name('cards.generate-batch');
        Route::patch('/cards/{card}/status', [CardManagementController::class, 'updateStatus'])->name('cards.update-status');

        // Winning Patterns (Phase 3)
        Route::get('/patterns', [PatternManagementController::class, 'index'])->name('patterns.index');
        Route::post('/patterns', [PatternManagementController::class, 'store'])->name('patterns.store');
        Route::patch('/patterns/{pattern}/toggle', [PatternManagementController::class, 'toggle'])->name('patterns.toggle');

        // Game Templates (Phase 9)
        Route::get('/templates', [TemplateManagementController::class, 'index'])->name('templates.index');
        Route::post('/templates', [TemplateManagementController::class, 'store'])->name('templates.store');
        Route::patch('/templates/{template}/toggle', [TemplateManagementController::class, 'toggle'])->name('templates.toggle');

        // Treasury & Financial Ledger (Phase 8)
        Route::get('/ledger', [LedgerManagementController::class, 'index'])->name('ledger.index');

        // Company Platform Credits (Phase 8 & Multitenant Billing)
        Route::get('/credits', [CompanyCreditController::class, 'index'])->name('credits.index');
        Route::post('/credits/buy', [CompanyCreditController::class, 'store'])->name('credits.store');

        // Company Payment Accounts (For player deposit receipts)
        Route::get('/payment-accounts', [CompanyPaymentAccountController::class, 'index'])->name('payment-accounts.index');
        Route::post('/payment-accounts', [CompanyPaymentAccountController::class, 'store'])->name('payment-accounts.store');
        Route::put('/payment-accounts/{paymentAccount}', [CompanyPaymentAccountController::class, 'update'])->name('payment-accounts.update');
        Route::delete('/payment-accounts/{paymentAccount}', [CompanyPaymentAccountController::class, 'destroy'])->name('payment-accounts.destroy');

        // Analytics & Reports (Phase 9)
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    });

// Player Tenant Routes
Route::middleware(['auth', 'tenant'])
    ->prefix('c/{company:slug}')
    ->name('player.')
    ->group(function () {
        Route::get('/dashboard', [PlayerDashboardController::class, 'index'])->name('dashboard');
        Route::get('/lobby', [GameLobbyController::class, 'index'])->name('lobby');
        Route::post('/games/{game}/join', [GameLobbyController::class, 'join'])->name('games.join');
        Route::get('/game/{game}', [GameLobbyController::class, 'show'])->name('game.show');
        Route::get('/game/{game}/state', [GameLobbyController::class, 'state'])->name('game.state');
        Route::post('/game/{game}/cards/{gameCard}/daub', [GameLobbyController::class, 'daub'])
            ->middleware('throttle:bingo.daub')
            ->name('game.cards.daub');
        Route::post('/game/{game}/cards/{gameCard}/claim-bingo', [BingoClaimController::class, 'claim'])
            ->middleware('throttle:bingo.claim')
            ->name('game.cards.claim-bingo');

        // Wallet & Financial Transactions (Phase 8)
        Route::get('/wallet', [WalletController::class, 'index'])->name('wallet.index');
        Route::post('/wallet/deposit', [WalletController::class, 'deposit'])
            ->middleware('throttle:wallet.operations')
            ->name('wallet.deposit');
        Route::post('/wallet/deposit-request', [WalletController::class, 'storeDepositRequest'])
            ->middleware('throttle:wallet.operations')
            ->name('wallet.deposit-request');
        Route::post('/wallet/withdraw', [WalletController::class, 'withdraw'])
            ->middleware('throttle:wallet.operations')
            ->name('wallet.withdraw');
    });

// Account Profile Routes
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
