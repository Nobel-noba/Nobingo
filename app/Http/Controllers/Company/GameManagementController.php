<?php

namespace App\Http\Controllers\Company;

use App\Domains\Cards\Models\BingoCard;
use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameCard;
use App\Domains\Games\Models\GameTemplate;
use App\Domains\Games\Services\CardAssignmentService;
use App\Domains\Games\Services\GameLifecycleService;
use App\Domains\Games\Services\NumberCallingService;
use App\Domains\Tenancy\Models\Company;
use App\Domains\Winners\Models\GameWinner;
use App\Domains\Winners\Services\BingoVerificationService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GameManagementController extends Controller
{
    public function __construct(
        protected GameLifecycleService $lifecycleService,
        protected NumberCallingService $callingService,
        protected BingoVerificationService $verificationService
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
            'players.assignedCard.card',
            'players.assignedCard.version',
            'cards.card',
            'cards.version',
            'cards.user',
            'calls',
            'lastCall',
            'winners.user',
            'winners.card.card',
            'winners.card.version',
            'winners.card.user',
            'winners.pattern',
        ]);

        $availableCardsCount = BingoCard::where('company_id', $company->id)
            ->where('status', BingoCard::STATUS_AVAILABLE)
            ->count();

        $availableCardNumbers = BingoCard::where('company_id', $company->id)
            ->where('status', BingoCard::STATUS_AVAILABLE)
            ->limit(50)
            ->pluck('card_number')
            ->toArray();

        $companyPlayers = User::where('company_id', $company->id)
            ->where('status', 'active')
            ->select(['id', 'name', 'email'])
            ->orderBy('name')
            ->get();

        $masterBoard = $this->callingService->getMasterBoard($game);
        $remainingCount = count($this->callingService->getRemainingNumbers($game));

        return Inertia::render('Company/Games/Show', [
            'game' => $game,
            'available_cards_count' => $availableCardsCount,
            'available_card_numbers' => $availableCardNumbers,
            'company_players' => $companyPlayers,
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

        if ($game->status === $validated['status']) {
            return redirect()->back()->with('info', "Game is already in status {$validated['status']}.");
        }

        if (in_array($validated['status'], [Game::STATUS_OPEN, Game::STATUS_STARTING, Game::STATUS_ACTIVE], true)) {
            if (! $company->hasSufficientCredit()) {
                return redirect()->back()
                    ->with('error', 'Insufficient platform credit. Company credit balance is $0.00. You must purchase platform credit before activating or starting games.')
                    ->withErrors([
                        'credit' => 'Company credit balance is $0.00. You must purchase platform credit before activating or starting games.',
                    ]);
            }
        }

        $this->lifecycleService->transitionTo($game, $validated['status']);

        return redirect()->back()->with('success', "Game transitioned to status {$validated['status']}.");
    }

    /**
     * Assign or reassign a specific card number to a player or walk-in guest in this game room.
     */
    public function assignCard(Company $company, Game $game, Request $request): RedirectResponse|JsonResponse
    {
        if ($game->company_id !== $company->id) {
            abort(404);
        }

        $validated = $request->validate([
            'is_walkin' => ['sometimes', 'boolean'],
            'user_id' => ['nullable', 'required_without:is_walkin', 'exists:users,id'],
            'card_number' => ['required', 'integer', 'min:1'],
            'guest_identifier' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $assignmentService = app(CardAssignmentService::class);

            if ($request->boolean('is_walkin')) {
                $gameCard = $assignmentService->assignWalkInCard(
                    $game,
                    (int) $validated['card_number'],
                    $validated['guest_identifier'] ?? null
                );
                $cardNumberFormatted = sprintf('#%06d', $validated['card_number']);
                $displayName = $gameCard->playerDisplayName();
                $message = "Card {$cardNumberFormatted} successfully assigned to cash walk-in player ({$displayName}).";
            } else {
                $user = User::findOrFail($validated['user_id']);
                $gameCard = $assignmentService->assignSpecificCardToPlayer(
                    $game,
                    $user,
                    (int) $validated['card_number']
                );
                $cardNumberFormatted = sprintf('#%06d', $validated['card_number']);
                $message = "Card {$cardNumberFormatted} successfully assigned to {$user->name}.";
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'game_card' => $gameCard,
                ]);
            }

            return redirect()->back()->with('success', $message);
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Confirm and approve a winning claim, release payout, and complete game.
     */
    public function confirmClaim(Company $company, Game $game, GameWinner $winner): RedirectResponse|JsonResponse
    {
        if ($game->company_id !== $company->id || $winner->game_id !== $game->id) {
            abort(404);
        }

        try {
            $result = $this->verificationService->confirmWinnerClaim($game, $winner);

            if (request()->wantsJson()) {
                return response()->json($result);
            }

            return redirect()->back()->with('success', $result['message']);
        } catch (\Throwable $e) {
            if (request()->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Reject a false claim, allowing host to resume the game session.
     */
    public function rejectClaim(Company $company, Game $game, GameWinner $winner, Request $request): RedirectResponse|JsonResponse
    {
        if ($game->company_id !== $company->id || $winner->game_id !== $game->id) {
            abort(404);
        }

        $reason = $request->input('reason', 'Host verified that card does not meet winning pattern requirements.');

        try {
            $result = $this->verificationService->rejectWinnerClaim($game, $winner, $reason);

            if ($request->wantsJson()) {
                return response()->json($result);
            }

            return redirect()->back()->with('success', $result['message']);
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Inspect and verify any card number (including walk-ins) with marked cells and completed patterns.
     */
    public function verifyCard(Company $company, Game $game, Request $request): JsonResponse
    {
        if ($game->company_id !== $company->id) {
            abort(404);
        }

        $validated = $request->validate([
            'card_number' => ['nullable', 'integer'],
            'game_card_id' => ['nullable', 'integer'],
        ]);

        $gameCardQuery = GameCard::where('game_id', $game->id)->with(['version', 'card', 'user']);

        if (! empty($validated['game_card_id'])) {
            $gameCard = $gameCardQuery->where('id', $validated['game_card_id'])->first();
        } elseif (! empty($validated['card_number'])) {
            $gameCard = $gameCardQuery->whereHas('card', function ($q) use ($validated) {
                $q->where('card_number', $validated['card_number']);
            })->first();
        } else {
            return response()->json(['error' => 'Must provide card_number or game_card_id.'], 422);
        }

        if (! $gameCard) {
            return response()->json(['error' => 'Card is not assigned in this game.'], 404);
        }

        $latestCall = $game->lastCall;
        $eval = $this->verificationService->verifyCard($game, $gameCard, $latestCall?->sequence_index);

        return response()->json([
            'is_valid' => $eval['is_valid'],
            'completed_count' => $eval['completed_count'],
            'required_count' => $eval['required_count'],
            'completed_patterns' => $eval['completed_patterns'],
            'completed_slugs' => $eval['completed_slugs'],
            'grid' => $gameCard->version?->grid,
            'marked_grid' => $eval['marked_grid'],
            'reason' => $eval['reason'],
            'card_number' => $gameCard->card?->card_number,
            'game_card_id' => $gameCard->id,
            'player_name' => $gameCard->playerDisplayName(),
            'is_walkin' => $gameCard->isWalkIn(),
            'estimated_payout' => $this->verificationService->calculatePrize($game, 1),
        ]);
    }

    /**
     * Declare a walk-in player as winner and finalize game.
     */
    public function declareWalkInWinner(Company $company, Game $game, Request $request): RedirectResponse|JsonResponse
    {
        if ($game->company_id !== $company->id) {
            abort(404);
        }

        $validated = $request->validate([
            'game_card_id' => ['nullable', 'integer'],
            'card_number' => ['nullable', 'integer'],
        ]);

        $gameCardQuery = GameCard::where('game_id', $game->id)->with(['version', 'card']);

        if (! empty($validated['game_card_id'])) {
            $gameCard = $gameCardQuery->where('id', $validated['game_card_id'])->first();
        } elseif (! empty($validated['card_number'])) {
            $gameCard = $gameCardQuery->whereHas('card', function ($q) use ($validated) {
                $q->where('card_number', $validated['card_number']);
            })->first();
        } else {
            return response()->json(['error' => 'Must provide card_number or game_card_id.'], 422);
        }

        if (! $gameCard) {
            return response()->json(['error' => 'Card is not assigned in this game.'], 404);
        }

        try {
            $result = $this->verificationService->declareWalkInWinner($game, $gameCard);

            if ($request->wantsJson()) {
                return response()->json($result);
            }

            return redirect()->back()->with('success', $result['message']);
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
