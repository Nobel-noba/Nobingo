<?php

namespace App\Http\Controllers\Player;

use App\Domains\Auth\Models\Role;
use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameCard;
use App\Domains\Games\Models\GamePlayer;
use App\Domains\Games\Services\CardAssignmentService;
use App\Domains\Games\Services\NumberCallingService;
use App\Domains\Tenancy\Models\Company;
use App\Domains\Tenancy\Models\GameManagerPlayer;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GameLobbyController extends Controller
{
    /**
     * Display the multiplayer bingo lobby for this company.
     */
    public function index(Company $company, Request $request): Response
    {
        $user = $request->user();

        $authorizedManagerIds = $user
            ? GameManagerPlayer::where('player_id', $user->id)->pluck('game_manager_id')->all()
            : [];

        $managerUserIds = User::where('company_id', $company->id)
            ->whereHas('roles', fn ($q) => $q->where('slug', Role::GAME_MANAGER))
            ->pluck('id')
            ->all();

        $openGames = Game::where('company_id', $company->id)
            ->whereIn('status', [Game::STATUS_OPEN, Game::STATUS_STARTING, Game::STATUS_ACTIVE])
            ->where(function ($q) use ($authorizedManagerIds, $managerUserIds) {
                $q->whereNull('created_by')
                    ->orWhereNotIn('created_by', $managerUserIds)
                    ->orWhereIn('created_by', $authorizedManagerIds);
            })
            ->with(['template', 'creator'])
            ->withCount('players')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (Game $game) use ($user, $company) {
                $hasJoined = $user ? GamePlayer::where('game_id', $game->id)->where('user_id', $user->id)->exists() : false;

                $managerBalance = 0;
                $formattedBalance = '$0.00';
                $hostName = $game->creator ? $game->creator->name : $company->name;
                $isHostedByManager = $game->creator && $game->creator->isGameManager() && ! $game->creator->isCompanyAdmin();

                if ($user) {
                    if ($isHostedByManager) {
                        $gmPlayer = GameManagerPlayer::where('game_manager_id', $game->created_by)
                            ->where('player_id', $user->id)
                            ->first();
                        $managerBalance = $gmPlayer ? $gmPlayer->balance : 0;
                        $formattedBalance = '$'.number_format($managerBalance / 100, 2);
                    } else {
                        $managerBalance = $user->balance;
                        $formattedBalance = $user->formattedBalance();
                    }
                }

                $isBalanceEmpty = $managerBalance <= 0;
                $isBalanceSufficient = $managerBalance >= $game->entry_fee && ! $isBalanceEmpty;

                return [
                    'id' => $game->id,
                    'game_number' => $game->game_number,
                    'name' => $game->name,
                    'description' => $game->description,
                    'status' => $game->status,
                    'entry_fee' => $game->entry_fee,
                    'formatted_entry_fee' => $game->formattedEntryFee(),
                    'players_count' => $game->players_count,
                    'max_players' => $game->max_players,
                    'template_name' => $game->template?->name ?? 'Custom Match',
                    'required_pattern_count' => data_get($game->configuration_snapshot, 'required_pattern_count', 1),
                    'has_joined' => $hasJoined,
                    'host_name' => $hostName,
                    'host_manager_id' => $game->created_by,
                    'player_balance' => $managerBalance,
                    'formatted_player_balance' => $formattedBalance,
                    'is_balance_empty' => $isBalanceEmpty,
                    'is_balance_sufficient' => $isBalanceSufficient,
                ];
            });

        return Inertia::render('Player/Lobby', [
            'games' => $openGames,
        ]);
    }

    /**
     * Join an open game room.
     */
    public function join(Company $company, Game $game, Request $request, CardAssignmentService $assignmentService): RedirectResponse
    {
        try {
            $assignmentService->joinGame($game, $request->user());

            return redirect()->route('player.game.show', [
                'company' => $company->slug,
                'game' => $game->id,
            ])->with('success', "You joined {$game->name}!");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * View the live game room interface for an active or starting game.
     */
    public function show(Company $company, Game $game, Request $request, NumberCallingService $callerService): Response|RedirectResponse
    {
        $user = $request->user();

        // Verify that the player has joined this game
        $gamePlayer = GamePlayer::where('game_id', $game->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $gamePlayer) {
            return redirect()->route('player.lobby', ['company' => $company->slug])
                ->with('error', 'You must join this game before entering the room.');
        }

        // Retrieve player's assigned fixed card for this game if already assigned by host
        $gameCard = GameCard::where('game_id', $game->id)
            ->where('user_id', $user->id)
            ->whereNull('released_at')
            ->with(['card', 'version'])
            ->first();

        $playerCardData = null;
        if ($gameCard && $gameCard->card && $gameCard->version) {
            $playerCardData = [
                'id' => $gameCard->id,
                'card_id' => $gameCard->card->id,
                'card_number' => $gameCard->card->formattedCardNumber(),
                'version' => $gameCard->version->version_number,
                'grid' => $gameCard->version->grid,
                'marked_positions' => $gameCard->getMarkedPositions(),
            ];
        }

        $game->load(['calls', 'lastCall']);

        return Inertia::render('Player/GameRoom', [
            'game' => [
                'id' => $game->id,
                'game_number' => $game->game_number,
                'name' => $game->name,
                'status' => $game->status,
                'call_interval' => $game->call_interval,
                'configuration' => $game->configuration_snapshot,
                'players_count' => $game->players()->count(),
                'entry_fee' => $game->formattedEntryFee(),
                'last_call' => $game->lastCall ? [
                    'sequence_index' => $game->lastCall->sequence_index,
                    'ball_number' => $game->lastCall->ball_number,
                    'letter' => $game->lastCall->letter,
                    'code' => $game->lastCall->displayCode(),
                    'called_at' => $game->lastCall->called_at?->toIso8601String(),
                ] : null,
                'calls' => $game->calls->map(fn ($c) => [
                    'sequence_index' => $c->sequence_index,
                    'ball_number' => $c->ball_number,
                    'letter' => $c->letter,
                    'code' => $c->displayCode(),
                    'called_at' => $c->called_at?->toIso8601String(),
                ]),
                'remaining_count' => count($callerService->getRemainingNumbers($game)),
            ],
            'player_card' => $playerCardData,
            'master_board' => $callerService->getMasterBoard($game),
        ]);
    }

    /**
     * Submit manual card daub coordinate.
     */
    public function daub(Company $company, Game $game, GameCard $gameCard, Request $request, NumberCallingService $callerService): JsonResponse
    {
        $user = $request->user();

        if ($gameCard->game_id !== $game->id || $gameCard->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized card access.'], 403);
        }

        $validated = $request->validate([
            'row' => ['required', 'integer', 'between:0,4'],
            'col' => ['required', 'integer', 'between:0,4'],
        ]);

        try {
            $callerService->manualDaub($gameCard, (int) $validated['row'], (int) $validated['col']);

            return response()->json([
                'success' => true,
                'marked_positions' => $gameCard->fresh()->getMarkedPositions(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Return live game and caller state for polling/real-time refresh.
     */
    public function state(Company $company, Game $game, Request $request, NumberCallingService $callerService): JsonResponse
    {
        $user = $request->user();

        $game->load(['lastCall', 'calls']);

        $gameCard = null;
        if ($user) {
            $gameCard = GameCard::where('game_id', $game->id)
                ->where('user_id', $user->id)
                ->first();
        }

        return response()->json([
            'game_status' => $game->status,
            'last_call' => $game->lastCall ? [
                'sequence_index' => $game->lastCall->sequence_index,
                'ball_number' => $game->lastCall->ball_number,
                'letter' => $game->lastCall->letter,
                'code' => $game->lastCall->displayCode(),
                'called_at' => $game->lastCall->called_at?->toIso8601String(),
            ] : null,
            'recent_calls' => $game->calls->take(-10)->values()->map(fn ($c) => [
                'sequence_index' => $c->sequence_index,
                'ball_number' => $c->ball_number,
                'letter' => $c->letter,
                'code' => $c->displayCode(),
            ]),
            'call_count' => $game->calls->count(),
            'remaining_count' => count($callerService->getRemainingNumbers($game)),
            'master_board' => $callerService->getMasterBoard($game),
            'marked_positions' => $gameCard ? $gameCard->getMarkedPositions() : [],
        ]);
    }
}
