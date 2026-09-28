<?php

use App\Domains\Tenancy\Models\Company;
use App\Http\Controllers\Company\CardManagementController;
use App\Http\Controllers\Company\CompanyDashboardController;
use App\Http\Controllers\Company\GameManagementController;
use App\Http\Controllers\Company\PatternManagementController;
use App\Http\Controllers\Platform\PlatformDashboardController;
use App\Http\Controllers\Player\GameLobbyController;
use App\Http\Controllers\Player\PlayerDashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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
    });

// Company Administration Routes
Route::middleware(['auth', 'tenant', 'role:PLATFORM_OWNER,COMPANY_ADMIN,GAME_MANAGER'])
    ->prefix('c/{company:slug}/admin')
    ->name('company.admin.')
    ->group(function () {
        Route::get('/', [CompanyDashboardController::class, 'index'])->name('dashboard');

        // Fixed Card Inventory (Phase 2)
        Route::get('/cards', [CardManagementController::class, 'index'])->name('cards.index');
        Route::get('/cards/{card}', [CardManagementController::class, 'show'])->name('cards.show');
        Route::post('/cards/generate-batch', [CardManagementController::class, 'generateBatch'])->name('cards.generate-batch');
        Route::patch('/cards/{card}/status', [CardManagementController::class, 'updateStatus'])->name('cards.update-status');

        // Winning Patterns (Phase 3)
        Route::get('/patterns', [PatternManagementController::class, 'index'])->name('patterns.index');
        Route::post('/patterns', [PatternManagementController::class, 'store'])->name('patterns.store');
        Route::patch('/patterns/{pattern}/toggle', [PatternManagementController::class, 'toggle'])->name('patterns.toggle');

        // Bingo Games (Phase 4 & 5)
        Route::get('/games', [GameManagementController::class, 'index'])->name('games.index');
        Route::get('/games/create', [GameManagementController::class, 'create'])->name('games.create');
        Route::post('/games', [GameManagementController::class, 'store'])->name('games.store');
        Route::get('/games/{game}', [GameManagementController::class, 'show'])->name('games.show');
        Route::patch('/games/{game}/status', [GameManagementController::class, 'updateStatus'])->name('games.update-status');
        Route::post('/games/{game}/call-next', [GameManagementController::class, 'callNext'])->name('games.call-next');
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
        Route::post('/game/{game}/cards/{gameCard}/daub', [GameLobbyController::class, 'daub'])->name('game.cards.daub');
    });

// Account Profile Routes
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
