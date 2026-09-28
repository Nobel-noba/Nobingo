<?php

namespace App\Http\Controllers\Company;

use App\Domains\Auth\Models\Role;
use App\Domains\Cards\Models\BingoCard;
use App\Domains\Tenancy\Models\Company;
use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class CompanyDashboardController extends Controller
{
    /**
     * Display the company administrator dashboard.
     */
    public function index(Company $company): Response
    {
        $stats = [
            'total_cards' => BingoCard::where('company_id', $company->id)->count(),
            'total_templates' => 0,  // Populated in Phase 3
            'active_games' => 0,     // Populated in Phase 4
            'total_players' => User::where('company_id', $company->id)
                ->whereHas('roles', fn ($q) => $q->where('slug', Role::PLAYER))
                ->count(),
        ];

        return Inertia::render('Company/Dashboard', [
            'stats' => $stats,
        ]);
    }
}
