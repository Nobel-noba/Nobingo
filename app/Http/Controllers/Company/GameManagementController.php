<?php

namespace App\Http\Controllers\Company;

use App\Domains\Cards\Models\BingoCard;
use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameTemplate;
use App\Domains\Games\Services\GameLifecycleService;
use App\Domains\Games\Services\NumberCallingService;
use App\Domains\Tenancy\Models\Company;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GameManagementController extends Controller
{
    public function __construct(
        protected GameLifecycleService $lifecycleService,
        protected NumberCallingService $callingService
    ) {}

    /**
     * Display all company games.
     */
    public function index(Company $company, Request $request): Response
    {
        $statusFilter = $request->query('status');

        $query = Game::where('company_id', $company->id)
            ->with(['template', 'creator'])
            ->withCount('players')
            ->orderBy('game_number', 'desc');

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        $games = $query->paginate(20)->withQueryString();
        $templates = GameTemplate::availableForCompany($company->id)->get();

        $stats = [
            'total' => Game::where('company_id', $company->id)->count(),
            'open' => Game::where('company_id', $company->id)->where('status', Game::STATUS_OPEN)->count(),
            'active' => Game::where('company_id', $company->id)->where('status', Game::STATUS_ACTIVE)->count(),
            'completed' => Game::where('company_id', $company->id)->where('status', Game::STATUS_COMPLETED)->count(),
        ];

        return Inertia::render('Company/Games/Index', [
            'games' => $games,
            'templates' => $templates,
            'filters' => ['status' => $statusFilter],
            'stats' => $stats,
        ]);
    }

    /**
     * Show game creation screen.
     */
    public function create(Company $company): Response
    {
        $templates = GameTemplate::availableForCompany($company->id)->get();

        return Inertia::render('Company/Games/Create', [
            'templates' => $templates,
        ]);
    }

    /**
     * Create and schedule a new game.
     */
    public function store(Company $company, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'game_template_id' => ['required', 'exists:game_templates,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'entry_fee' => ['nullable', 'integer', 'min:0'],
            'min_players' => ['nullable', 'integer', 'min:1'],
            'max_players' => ['nullable', 'integer', 'min:2', 'max:500'],
            'call_interval' => ['nullable', 'integer', 'min:2', 'max:60'],
            'auto_open' => ['nullable', 'boolean'],
        ]);

        $template = GameTemplate::findOrFail($validated['game_template_id']);

        $overrides = array_filter([
            'name' => $validated['name'] ?? null,
            'description' => $validated['description'] ?? null,
            'entry_fee' => isset($validated['entry_fee']) ? (int) $validated['entry_fee'] : null,
            'min_players' => isset($validated['min_players']) ? (int) $validated['min_players'] : null,
            'max_players' => isset($validated['max_players']) ? (int) $validated['max_players'] : null,
            'call_interval' => isset($validated['call_interval']) ? (int) $validated['call_interval'] : null,
            'status' => ! empty($validated['auto_open']) ? Game::STATUS_OPEN : Game::STATUS_DRAFT,
        ]);

        $game = $this->lifecycleService->createFromTemplate(
            $template,
            $company,
            $overrides,
            $request->user()->id
        );

        return redirect()->route('company.admin.games.show', [
            'company' => $company->slug,
            'game' => $game->id,
        ])->with('success', "Game #{$game->game_number} created successfully.");
    }

    /**
     * Display a specific game room and operator controls.
     */
    public function show(Company $company, Game $game): Response
    {
        if ($game->company_id !== $company->id) {
            abort(404);
        }

        $game->load([
            'template',
            'creator',
            'players.user',
            'cards.card',
            'cards.version',
            'calls',
            'lastCall',
        ]);

        $availableCardsCount = BingoCard::where('company_id', $company->id)
            ->where('status', BingoCard::STATUS_AVAILABLE)
            ->count();

        $masterBoard = $this->callingService->getMasterBoard($game);
        $remainingCount = count($this->callingService->getRemainingNumbers($game));

        return Inertia::render('Company/Games/Show', [
            'game' => $game,
            'available_cards_count' => $availableCardsCount,
            'master_board' => $masterBoard,
            'remaining_count' => $remainingCount,
        ]);
    }

    /**
     * Draw and call the next random ball.
     */
    public function callNext(Company $company, Game $game, Request $request): RedirectResponse|JsonResponse
    {
        if ($game->company_id !== $company->id) {
            abort(404);
        }

        if (! $game->isActive()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => "Cannot call number when game is in status: {$game->status}"], 422);
            }

            return redirect()->back()->with('error', "Cannot call number when game is in status: {$game->status}");
        }

        try {
            $call = $this->callingService->callNextNumber($game);

            if (! $call) {
                if ($request->wantsJson()) {
                    return response()->json(['message' => 'All numbers have been called.'], 200);
                }

                return redirect()->back()->with('warning', 'All 75 numbers have been called.');
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'call' => $call,
                    'remaining_count' => count($this->callingService->getRemainingNumbers($game)),
                    'master_board' => $this->callingService->getMasterBoard($game),
                ]);
            }

            return redirect()->back()->with('success', "Ball called: {$call->displayCode()} (#{$call->sequence_index}/75)");
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 400);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Transition game status (open, start, pause, resume, cancel).
     */
    public function updateStatus(Company $company, Game $game, Request $request): RedirectResponse
    {
        if ($game->company_id !== $company->id) {
            abort(404);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', [
                Game::STATUS_OPEN,
                Game::STATUS_STARTING,
                Game::STATUS_ACTIVE,
                Game::STATUS_PAUSED,
                Game::STATUS_COMPLETED,
                Game::STATUS_CANCELLED,
            ])],
        ]);

        $this->lifecycleService->transitionTo($game, $validated['status']);

        return redirect()->back()->with('success', "Game transitioned to status {$validated['status']}.");
    }
}
