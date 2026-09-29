<?php

namespace App\Http\Controllers\Player;

use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameCall;
use App\Domains\Games\Models\GameCard;
use App\Domains\Tenancy\Models\Company;
use App\Domains\Winners\Models\GameWinner;
use App\Domains\Winners\Services\BingoVerificationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BingoClaimController extends Controller
{
    public function __construct(
        protected BingoVerificationService $verificationService
    ) {}

    /**
     * Submit an authoritative Bingo win claim for a specific card in an active game.
     */
    public function claim(Company $company, Game $game, GameCard $gameCard, Request $request): JsonResponse
    {
        $user = $request->user();

        // Enforce tenant boundary
        if ((int) $game->company_id !== (int) $company->id) {
            abort(404, 'Game not found in this company.');
        }

        // Check account status
        if (! $user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is suspended. You cannot submit claims.',
            ], 403);
        }

        $result = $this->verificationService->claimBingo(
            $game,
            $gameCard,
            $user,
            GameWinner::CLAIM_TYPE_MANUAL,
            autoConfirm: false
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'evaluation' => $result['evaluation'] ?? null,
            ], 422);
        }

        $winner = $result['winner'];

        return response()->json([
            'success' => true,
            'status' => $result['status'] ?? 'pending_verification',
            'message' => $result['message'],
            'winner' => [
                'id' => $winner->id,
                'user_id' => $winner->user_id,
                'game_card_id' => $winner->game_card_id,
                'winning_ball' => sprintf('%s-%d', GameCall::getLetterForNumber($winner->winning_ball_number), $winner->winning_ball_number),
                'winning_call_sequence' => $winner->winning_call_sequence,
                'payout_amount' => $winner->payout_amount,
                'formatted_payout' => $winner->formattedPayout(),
                'payout_status' => $winner->payout_status,
                'claimed_at' => $winner->claimed_at->toIso8601String(),
            ],
        ]);
    }
}
