<?php

namespace App\Http\Controllers\Company;

use App\Domains\Games\Models\Game;
use App\Domains\Tenancy\Models\Company;
use App\Domains\Winners\Models\GameWinner;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WinnerManagementController extends Controller
{
    /**
     * Display a listing of verified winners for this tenant company.
     */
    public function index(Company $company, Request $request): Response
    {
        $gameId = $request->query('game_id');
        $search = $request->query('search');

        $query = GameWinner::where('company_id', $company->id)
            ->with(['game', 'card.card', 'user', 'pattern'])
            ->orderByDesc('claimed_at');

        if ($gameId) {
            $query->where('game_id', $gameId);
        }

        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $winners = $query->paginate(20)->withQueryString();

        $stats = [
            'total_winners' => GameWinner::where('company_id', $company->id)->count(),
            'total_payout' => GameWinner::where('company_id', $company->id)->sum('payout_amount'),
            'manual_claims' => GameWinner::where('company_id', $company->id)->where('claim_type', GameWinner::CLAIM_TYPE_MANUAL)->count(),
            'automatic_claims' => GameWinner::where('company_id', $company->id)->where('claim_type', GameWinner::CLAIM_TYPE_AUTOMATIC)->count(),
        ];

        $games = Game::where('company_id', $company->id)
            ->whereHas('winners')
            ->select('id', 'game_number', 'name')
            ->orderByDesc('game_number')
            ->get();

        return Inertia::render('Company/Winners/Index', [
            'winners' => $winners,
            'games' => $games,
            'filters' => [
                'game_id' => $gameId,
                'search' => $search,
            ],
            'stats' => $stats,
        ]);
    }
}
