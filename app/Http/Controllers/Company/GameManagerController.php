<?php

namespace App\Http\Controllers\Company;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Audit\Services\AuditLogger;
use App\Domains\Auth\Models\Role;
use App\Domains\Tenancy\Models\Company;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GameManagerController extends Controller
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Display a listing of Game Managers for the company.
     */
    public function index(Request $request, Company $company): Response
    {
        $managers = User::where('company_id', $company->id)
            ->whereHas('roles', fn ($q) => $q->where('slug', Role::GAME_MANAGER))
            ->withCount('createdGames')
            ->latest('id')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status ?? 'active',
                'games_count' => $user->created_games_count,
                'created_at' => $user->created_at->format('M d, Y'),
            ]);

        return Inertia::render('Company/GameManagers/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'managers' => $managers,
        ]);
    }

    /**
     * Store a newly created Game Manager in storage.
     */
    public function store(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'max:100'],
        ]);

        $manager = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'company_id' => $company->id,
            'status' => 'active',
            'balance' => 0,
            'must_reset_password' => false,
        ]);

        $managerRole = Role::firstOrCreate(
            ['slug' => Role::GAME_MANAGER],
            ['name' => 'Game Manager', 'description' => 'Operator running games, caller, and counter operations.']
        );
        $manager->roles()->sync([$managerRole->id]);

        $this->auditLogger->log(
            AuditLog::ACTION_PLAYER_ACTIVATED,
            $manager,
            "Company Admin created new Game Manager {$manager->name} ({$manager->email}).",
            [
                'manager_id' => $manager->id,
                'role' => Role::GAME_MANAGER,
            ]
        );

        return back()->with('success', "Game Manager {$manager->name} created successfully.");
    }

    /**
     * Update the specified Game Manager.
     */
    public function update(Request $request, Company $company, User $gameManager): RedirectResponse
    {
        if ((int) $gameManager->company_id !== (int) $company->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($gameManager->id)],
            'status' => ['required', 'string', 'in:active,suspended'],
        ]);

        $gameManager->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'status' => $validated['status'],
        ]);

        $this->auditLogger->log(
            AuditLog::ACTION_PLAYER_ACTIVATED,
            $gameManager,
            "Company Admin updated Game Manager {$gameManager->name}.",
            [
                'manager_id' => $gameManager->id,
                'status' => $validated['status'],
            ]
        );

        return back()->with('success', "Game Manager {$gameManager->name} updated successfully.");
    }

    /**
     * Reset password for the Game Manager.
     */
    public function resetPassword(Request $request, Company $company, User $gameManager): RedirectResponse
    {
        if ((int) $gameManager->company_id !== (int) $company->id) {
            abort(404);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:6', 'max:100'],
        ]);

        $gameManager->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->auditLogger->log(
            AuditLog::ACTION_PLAYER_ACTIVATED,
            $gameManager,
            "Company Admin updated password for Game Manager {$gameManager->name}.",
            [
                'manager_id' => $gameManager->id,
            ]
        );

        return back()->with('success', "Password successfully updated for {$gameManager->name}.");
    }

    /**
     * Remove / Delete the specified Game Manager.
     */
    public function destroy(Company $company, User $gameManager): RedirectResponse
    {
        if ((int) $gameManager->company_id !== (int) $company->id) {
            abort(404);
        }

        $name = $gameManager->name;

        // Detach roles and delete or deactivate
        $gameManager->roles()->detach();
        $gameManager->delete();

        $this->auditLogger->log(
            AuditLog::ACTION_PLAYER_SUSPENDED,
            $company,
            "Company Admin removed Game Manager {$name}.",
            [
                'deleted_user' => $name,
            ]
        );

        return back()->with('success', "Game Manager {$name} has been removed.");
    }
}
