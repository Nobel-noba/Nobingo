<?php

namespace App\Http\Controllers\Company;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Audit\Services\AuditLogger;
use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameTemplate;
use App\Domains\Patterns\Models\WinningPattern;
use App\Domains\Tenancy\Models\Company;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TemplateManagementController extends Controller
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Display a list of game templates for this tenant company.
     */
    public function index(Company $company): Response
    {
        $templates = GameTemplate::forCompany($company->id)
            ->latest('id')
            ->get()
            ->map(fn (GameTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'description' => $t->description,
                'pattern_mode' => $t->pattern_mode,
                'required_pattern_count' => $t->required_pattern_count,
                'allowed_pattern_ids' => $t->allowed_pattern_ids ?? [],
                'winner_policy' => $t->winner_policy,
                'default_call_interval' => $t->default_call_interval,
                'default_min_players' => $t->default_min_players,
                'default_max_players' => $t->default_max_players,
                'default_entry_fee' => $t->default_entry_fee,
                'formatted_entry_fee' => '$'.number_format($t->default_entry_fee / 100, 2),
                'default_prize_configuration' => $t->default_prize_configuration,
                'is_active' => $t->is_active,
                'is_global' => $t->company_id === null,
            ]);

        $patterns = WinningPattern::where('is_active', true)->get(['id', 'name', 'slug', 'type']);

        return Inertia::render('Company/Templates/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'templates' => $templates,
            'patterns' => $patterns,
        ]);
    }

    /**
     * Store a newly created game template for this company.
     */
    public function store(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'pattern_mode' => ['required', 'string', 'in:single_pattern,multi_pattern,progressive'],
            'required_pattern_count' => ['required', 'integer', 'min:1', 'max:15'],
            'allowed_pattern_ids' => ['required', 'array', 'min:1'],
            'allowed_pattern_ids.*' => ['exists:winning_patterns,id'],
            'winner_policy' => ['required', 'string', 'in:first_valid,simultaneous'],
            'default_call_interval' => ['required', 'integer', 'min:2', 'max:60'],
            'default_min_players' => ['required', 'integer', 'min:1', 'max:500'],
            'default_max_players' => ['required', 'integer', 'min:1', 'max:1000', 'gte:default_min_players'],
            'default_entry_fee' => ['required', 'integer', 'min:0'], // in dollars
            'fixed_prize' => ['nullable', 'integer', 'min:0'], // in dollars
        ]);

        $entryFeeCents = $validated['default_entry_fee'] * 100;
        $fixedPrizeCents = isset($validated['fixed_prize']) ? $validated['fixed_prize'] * 100 : 10000;

        $slugBase = Str::slug($validated['name']);
        $uniqueSlug = $slugBase.'-'.bin2hex(random_bytes(3));

        $template = GameTemplate::create([
            'company_id' => $company->id,
            'name' => $validated['name'],
            'slug' => $uniqueSlug,
            'description' => $validated['description'] ?? null,
            'pattern_mode' => $validated['pattern_mode'],
            'required_pattern_count' => $validated['required_pattern_count'],
            'allowed_pattern_ids' => $validated['allowed_pattern_ids'],
            'winner_policy' => $validated['winner_policy'],
            'default_call_interval' => $validated['default_call_interval'],
            'default_min_players' => $validated['default_min_players'],
            'default_max_players' => $validated['default_max_players'],
            'default_entry_fee' => $entryFeeCents,
            'default_prize_configuration' => [
                'fixed_prize' => $fixedPrizeCents,
            ],
            'is_active' => true,
        ]);

        $this->auditLogger->log(
            AuditLog::ACTION_TEMPLATE_CREATED,
            $template,
            "Created reusable game template {$template->name}.",
            ['template_id' => $template->id, 'slug' => $template->slug]
        );

        return back()->with('success', "Game template {$template->name} created successfully!");
    }

    /**
     * Toggle active status of a template.
     */
    public function toggle(Company $company, GameTemplate $template): RedirectResponse
    {
        if ($template->company_id !== null && (int) $template->company_id !== (int) $company->id) {
            abort(403);
        }

        $template->update(['is_active' => ! $template->is_active]);

        return back()->with('success', "Template {$template->name} status toggled.");
    }
}
