<?php

namespace App\Http\Controllers\Player;

use App\Domains\Tenancy\Models\Company;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlayerDashboardController extends Controller
{
    /**
     * Display the player hub dashboard.
     */
    public function index(Company $company, Request $request): Response
    {
        $user = $request->user();

        $stats = [
            'balance' => $user->formattedBalance(),
            'games_played' => 0,  // Populated in Phase 4
            'games_won' => 0,     // Populated in Phase 7
            'active_cards' => 0,  // Populated in Phase 4
        ];

        return Inertia::render('Player/Dashboard', [
            'stats' => $stats,
        ]);
    }
}
