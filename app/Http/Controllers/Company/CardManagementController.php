<?php

namespace App\Http\Controllers\Company;

use App\Domains\Cards\Models\BingoCard;
use App\Domains\Cards\Services\BingoCardGenerator;
use App\Domains\Tenancy\Models\Company;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CardManagementController extends Controller
{
    /**
     * Display the company's fixed card inventory.
     */
    public function index(Company $company, Request $request): Response
    {
        $statusFilter = $request->query('status');
        $search = $request->query('search');

        $query = BingoCard::where('company_id', $company->id)
            ->with('currentVersion')
            ->orderBy('card_number', 'asc');

        if ($statusFilter && in_array($statusFilter, [
            BingoCard::STATUS_AVAILABLE,
            BingoCard::STATUS_RESERVED,
            BingoCard::STATUS_ASSIGNED,
            BingoCard::STATUS_IN_USE,
            BingoCard::STATUS_LOCKED,
            BingoCard::STATUS_RETIRED,
            BingoCard::STATUS_DISABLED,
        ], true)) {
            $query->where('status', $statusFilter);
        }

        if ($search) {
            $cleaned = preg_replace('/[^0-9]/', '', $search);
            if ($cleaned !== '') {
                $query->where('card_number', (int) $cleaned);
            }
        }

        $cards = $query->paginate(25)->withQueryString();

        $stats = [
            'total' => BingoCard::where('company_id', $company->id)->count(),
            'available' => BingoCard::where('company_id', $company->id)->where('status', BingoCard::STATUS_AVAILABLE)->count(),
            'assigned' => BingoCard::where('company_id', $company->id)->where('status', BingoCard::STATUS_ASSIGNED)->count(),
            'in_use' => BingoCard::where('company_id', $company->id)->where('status', BingoCard::STATUS_IN_USE)->count(),
            'retired' => BingoCard::where('company_id', $company->id)->where('status', BingoCard::STATUS_RETIRED)->count(),
            'disabled' => BingoCard::where('company_id', $company->id)->where('status', BingoCard::STATUS_DISABLED)->count(),
        ];

        return Inertia::render('Company/Cards/Index', [
            'cards' => $cards,
            'filters' => [
                'status' => $statusFilter,
                'search' => $search,
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * Display a specific card with full version history and layout.
     */
    public function show(Company $company, BingoCard $card): Response
    {
        // Enforce company isolation
        if ($card->company_id !== $company->id) {
            abort(404);
        }

        $card->load(['currentVersion', 'versions.creator']);

        return Inertia::render('Company/Cards/Show', [
            'card' => $card,
            'formatted_number' => $card->formattedCardNumber(),
        ]);
    }

    /**
     * Generate a new batch of fixed cards for this company.
     */
    public function generateBatch(Company $company, Request $request, BingoCardGenerator $generator): RedirectResponse
    {
        $validated = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $count = (int) $validated['count'];
        $generator->generateBatch($company, $count, $request->user()->id);

        return redirect()->back()->with('success', "Successfully generated {$count} fixed cards.");
    }

    /**
     * Update the status of a fixed card (e.g. activate, retire, disable).
     */
    public function updateStatus(Company $company, BingoCard $card, Request $request): RedirectResponse
    {
        if ($card->company_id !== $company->id) {
            abort(404);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', [
                BingoCard::STATUS_AVAILABLE,
                BingoCard::STATUS_RETIRED,
                BingoCard::STATUS_DISABLED,
            ])],
        ]);

        $card->update(['status' => $validated['status']]);

        return redirect()->back()->with('success', "Card #{$card->formattedCardNumber()} status updated to {$validated['status']}.");
    }
}
