<?php

namespace App\Http\Middleware;

use App\Domains\Tenancy\Services\CompanyContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        if ($user) {
            $user->loadMissing('roles', 'company');
        }

        $activeCompany = app(CompanyContext::class)->getCompany() ?? $user?->company;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'balance' => $user->balance,
                    'formatted_balance' => $user->formattedBalance(),
                    'company_id' => $user->company_id,
                    'roles' => $user->roles->pluck('slug')->all(),
                    'is_platform_owner' => $user->isPlatformOwner(),
                    'is_company_admin' => $user->isCompanyAdmin(),
                    'is_game_manager' => $user->isGameManager(),
                    'is_player' => $user->isPlayer(),
                ] : null,
            ],
            'tenant' => $activeCompany ? [
                'id' => $activeCompany->id,
                'name' => $activeCompany->name,
                'slug' => $activeCompany->slug,
                'status' => $activeCompany->status,
                'settings' => $activeCompany->settings,
            ] : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
