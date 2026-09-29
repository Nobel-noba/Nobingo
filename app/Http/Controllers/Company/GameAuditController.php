<?php

namespace App\Http\Controllers\Company;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameCall;
use App\Domains\Games\Models\GameCard;
use App\Domains\Games\Models\GamePlayer;
use App\Domains\Tenancy\Models\Company;
use App\Domains\Winners\Models\GameWinner;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class GameAuditController extends Controller
{
    /**
     * Display a complete chronological audit, replay, and validation breakdown for a specific game.
     */
    public function show(Company $company, Game $game): Response
    {
        if ((int) $game->company_id !== (int) $company->id) {
            abort(404, 'Game does not belong to this company.');
        }

        // 1. Called Numbers Sequence
        $calls = GameCall::where('game_id', $game->id)
            ->orderBy('sequence_index')
            ->get(['sequence_index', 'ball_number', 'letter', 'called_at'])
            ->map(fn ($c) => [
                'sequence_index' => $c->sequence_index,
                'ball_number' => $c->ball_number,
                'letter' => $c->letter,
                'display' => $c->letter.$c->ball_number,
                'called_at' => $c->called_at ? $c->called_at->format('H:i:s') : '-',
            ]);

        // 2. Participating Players and Assigned Cards
        $players = GamePlayer::where('game_id', $game->id)
            ->with(['user:id,name,email'])
            ->get()
            ->map(function ($gp) use ($game) {
                $gameCard = GameCard::where('game_id', $game->id)
                    ->where('user_id', $gp->user_id)
                    ->with('card:id,card_number', 'version:id,grid')
                    ->first();

                return [
                    'user_id' => $gp->user_id,
                    'name' => $gp->user->name ?? 'Player',
                    'email' => $gp->user->email ?? '',
                    'card_number' => $gameCard?->card?->card_number ?? '#---',
                    'card_grid' => $gameCard?->version?->grid ?? [],
                    'joined_at' => $gp->joined_at ? $gp->joined_at->format('M d, Y H:i:s') : '-',
                ];
            });

        // 3. Winners & Explanation Breakdown
        $calledNumbersList = $calls->pluck('ball_number')->all();

        $winners = GameWinner::where('game_id', $game->id)
            ->with(['user:id,name,email', 'card.card', 'pattern'])
            ->get()
            ->map(fn (GameWinner $w) => [
                'id' => $w->id,
                'user_name' => $w->user->name ?? 'Player',
                'user_email' => $w->user->email ?? '',
                'card_number' => $w->card?->card?->card_number ?? '#---',
                'pattern_name' => $w->pattern->name ?? 'Standard',
                'winning_call_sequence' => $w->winning_call_sequence,
                'winning_ball' => GameCall::getLetterForNumber($w->winning_ball_number).$w->winning_ball_number,
                'payout_amount' => $w->payout_amount,
                'formatted_payout' => '$'.number_format($w->payout_amount / 100, 2),
                'payout_status' => $w->payout_status,
                'claim_type' => $w->claim_type,
                'claimed_at' => $w->claimed_at->format('M d, Y H:i:s'),
                'patterns_snapshot' => $w->winning_patterns_snapshot,
                'validation_verdict' => 'VALID WINNER — Verified against server call log on sequence #'.$w->winning_call_sequence,
            ]);

        // 4. Audit Log Events for this Game
        $auditLogs = AuditLog::where('company_id', $company->id)
            ->where(function ($q) use ($game) {
                $q->where(function ($sub) use ($game) {
                    $sub->where('auditable_type', Game::class)
                        ->where('auditable_id', $game->id);
                })->orWhere('details->game_id', $game->id);
            })
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'created_at' => $log->created_at->format('M d, Y H:i:s'),
            ]);

        return Inertia::render('Company/Games/Audit', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'game' => [
                'id' => $game->id,
                'game_number' => $game->game_number,
                'name' => $game->name,
                'status' => $game->status,
                'entry_fee' => $game->entry_fee,
                'formatted_entry_fee' => $game->formattedEntryFee(),
                'winner_policy' => $game->winner_policy,
                'call_interval' => $game->call_interval,
                'configuration' => $game->configuration_snapshot,
                'started_at' => $game->started_at ? $game->started_at->format('M d, Y H:i:s') : null,
                'ended_at' => $game->ended_at ? $game->ended_at->format('M d, Y H:i:s') : null,
            ],
            'calls' => $calls,
            'players' => $players,
            'winners' => $winners,
            'audit_logs' => $auditLogs,
        ]);
    }
}
