<?php

namespace App\Http\Controllers\Company;

use App\Domains\Patterns\Models\WinningPattern;
use App\Domains\Patterns\Services\WinningPatternService;
use App\Domains\Tenancy\Models\Company;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PatternManagementController extends Controller
{
    /**
     * Display winning patterns available to this company.
     */
    public function index(Company $company): Response
    {
        $patterns = WinningPattern::availableForCompany($company->id)
            ->orderBy('type', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        return Inertia::render('Company/Patterns/Index', [
            'patterns' => $patterns,
        ]);
    }

    /**
     * Create a custom company-scoped winning pattern.
     */
    public function store(Company $company, Request $request, WinningPatternService $service): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', 'string', 'in:line,column,diagonal,special,full_card'],
            'coordinates' => ['required', 'array', 'min:1'],
        ]);

        $coordValidation = $service->validateCoordinates($validated['coordinates']);
        if (! $coordValidation['is_valid']) {
            return redirect()->back()->withErrors(['coordinates' => implode('; ', $coordValidation['errors'])]);
        }

        // Check unique slug within company
        $existing = WinningPattern::where('slug', $validated['slug'])
            ->where(function ($q) use ($company) {
                $q->whereNull('company_id')->orWhere('company_id', $company->id);
            })
            ->exists();

        if ($existing) {
            return redirect()->back()->withErrors(['slug' => 'A pattern with this slug already exists.']);
        }

        WinningPattern::create([
            'company_id' => $company->id,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'coordinates' => $coordValidation['normalized_coordinates'],
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', "Custom pattern '{$validated['name']}' created successfully.");
    }

    /**
     * Toggle pattern active status.
     */
    public function toggle(Company $company, WinningPattern $pattern): RedirectResponse
    {
        if ($pattern->company_id !== null && $pattern->company_id !== $company->id) {
            abort(404);
        }

        $pattern->update(['is_active' => ! $pattern->is_active]);

        $status = $pattern->is_active ? 'activated' : 'deactivated';

        return redirect()->back()->with('success', "Pattern '{$pattern->name}' has been {$status}.");
    }
}
