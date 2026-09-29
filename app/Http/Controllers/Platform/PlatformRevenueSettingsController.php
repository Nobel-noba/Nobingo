<?php

namespace App\Http\Controllers\Platform;

use App\Domains\Platform\Models\PlatformSetting;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformRevenueSettingsController extends Controller
{
    /**
     * Display current platform revenue and pot split settings.
     */
    public function index(): Response
    {
        $settings = PlatformSetting::getRevenueSettings();

        return Inertia::render('Platform/RevenueSettings', [
            'settings' => $settings,
        ]);
    }

    /**
     * Save platform revenue and pot split settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'winner_share_percentage' => ['required', 'numeric', 'min:10', 'max:99'],
            'platform_fee_percentage' => ['required', 'numeric', 'min:0', 'max:50'],
        ]);

        PlatformSetting::set('revenue_settings', [
            'winner_share_percentage' => (float) $validated['winner_share_percentage'],
            'platform_fee_percentage' => (float) $validated['platform_fee_percentage'],
        ]);

        return back()->with('success', 'Platform revenue sharing settings saved successfully.');
    }
}
