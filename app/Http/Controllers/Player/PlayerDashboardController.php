<?php

namespace App\Http\Controllers\Player;

use App\Domains\Games\Models\GameCard;
use App\Domains\Games\Models\GamePlayer;
use App\Domains\Tenancy\Models\Company;
use App\Domains\Winners\Models\GameWinner;
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
            'games_played' => GamePlayer::where('user_id', $user->id)->count(),
            'games_won' => GameWinner::where('user_id', $user->id)->count(),
            'active_cards' => GameCard::where('user_id', $user->id)->whereNull('released_at')->count(),
        ];

        return Inertia::render('Player/Dashboard', [
            'stats' => $stats,
        ]);
    }
}
