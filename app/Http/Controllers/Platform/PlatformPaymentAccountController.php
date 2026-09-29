<?php

namespace App\Http\Controllers\Platform;

use App\Domains\Financial\Models\PaymentAccount;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformPaymentAccountController extends Controller
{
    /**
     * List payment accounts configured by platform owner for company credit purchases.
     */
    public function index(): Response
    {
        $accounts = PaymentAccount::forPlatform()
            ->latest('id')
            ->get();

        return Inertia::render('Platform/PaymentAccounts/Index', [
            'accounts' => $accounts,
        ]);
    }

    /**
     * Create a new platform payment account.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider_name' => ['required', 'string', 'max:100'],
            'account_name' => ['required', 'string', 'max:150'],
            'account_number' => ['required', 'string', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        PaymentAccount::create([
            'company_id' => null, // Platform account
            'provider_name' => $validated['provider_name'],
            'account_name' => $validated['account_name'],
            'account_number' => $validated['account_number'],
            'instructions' => $validated['instructions'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Platform payment account created successfully.');
    }

    /**
     * Update an existing platform payment account.
     */
    public function update(Request $request, PaymentAccount $paymentAccount): RedirectResponse
    {
        if ($paymentAccount->company_id !== null) {
            abort(404);
        }

        $validated = $request->validate([
            'provider_name' => ['required', 'string', 'max:100'],
            'account_name' => ['required', 'string', 'max:150'],
            'account_number' => ['required', 'string', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        $paymentAccount->update([
            'provider_name' => $validated['provider_name'],
            'account_name' => $validated['account_name'],
            'account_number' => $validated['account_number'],
            'instructions' => $validated['instructions'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Platform payment account updated successfully.');
    }

    /**
     * Delete a platform payment account.
     */
    public function destroy(PaymentAccount $paymentAccount): RedirectResponse
    {
        if ($paymentAccount->company_id !== null) {
            abort(404);
        }

        $paymentAccount->delete();

        return back()->with('success', 'Platform payment account deleted.');
    }
}
