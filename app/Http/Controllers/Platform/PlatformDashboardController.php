<?php

namespace App\Http\Controllers\Platform;

use App\Domains\Auth\Models\Role;
use App\Domains\Tenancy\Models\Company;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformDashboardController extends Controller
{
    /**
     * Display the platform owner master dashboard.
     */
    public function index(): Response
    {
        $companies = Company::withCount('users')->latest()->get();

        $stats = [
            'total_companies' => Company::count(),
            'active_companies' => Company::where('status', 'active')->count(),
            'total_users' => User::count(),
            'total_players' => User::whereHas('roles', fn ($q) => $q->where('slug', Role::PLAYER))->count(),
        ];

        return Inertia::render('Platform/Dashboard', [
            'stats' => $stats,
            'companies' => $companies,
        ]);
    }

    /**
     * Display the company tenant directory.
     */
    public function companies(): Response
    {
        $companies = Company::withCount('users')->latest()->get();

        return Inertia::render('Platform/Companies', [
            'companies' => $companies,
        ]);
    }

    /**
     * Provision a new company tenant.
     */
    public function storeCompany(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'unique:companies,slug', 'regex:/^[a-z0-9\-]+$/'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'brand_color' => ['nullable', 'string', 'max:20', 'regex:/^#[a-fA-F0-9]{3,6}$/'],
        ]);

        Company::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'status' => 'active',
            'settings' => [
                'tagline' => $validated['tagline'] ?? null,
                'brand_color' => $validated['brand_color'] ?? '#4f46e5',
                'currency' => 'USD',
            ],
        ]);

        return redirect()->back()->with('success', "Company {$validated['name']} provisioned successfully.");
    }
}
