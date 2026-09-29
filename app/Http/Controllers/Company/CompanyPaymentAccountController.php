<?php

namespace App\Http\Controllers\Company;

use App\Domains\Financial\Models\PaymentAccount;
use App\Domains\Tenancy\Models\Company;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyPaymentAccountController extends Controller
{
    /**
     * List payment accounts configured by the company for player deposits.
     */
    public function index(Company $company): Response
    {
        $accounts = $company->paymentAccounts()
            ->latest('id')
            ->get();

        return Inertia::render('Company/PaymentAccounts/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'accounts' => $accounts,
        ]);
    }

    /**
     * Add a new payment account for this company.
     */
    public function store(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'provider_name' => ['required', 'string', 'max:100'],
            'account_name' => ['required', 'string', 'max:150'],
            'account_number' => ['required', 'string', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        $company->paymentAccounts()->create([
            'provider_name' => $validated['provider_name'],
            'account_name' => $validated['account_name'],
            'account_number' => $validated['account_number'],
            'instructions' => $validated['instructions'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Payment account added successfully.');
    }

    /**
     * Update an existing payment account.
     */
    public function update(Request $request, Company $company, PaymentAccount $paymentAccount): RedirectResponse
    {
        if ($paymentAccount->company_id !== $company->id) {
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

        return back()->with('success', 'Payment account updated successfully.');
    }

    /**
     * Delete a payment account.
     */
    public function destroy(Company $company, PaymentAccount $paymentAccount): RedirectResponse
    {
        if ($paymentAccount->company_id !== $company->id) {
            abort(404);
        }

        $paymentAccount->delete();

        return back()->with('success', 'Payment account deleted.');
    }
}
