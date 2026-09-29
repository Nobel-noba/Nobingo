<?php

namespace App\Domains\Winners\Services;

use App\Domains\Financial\Services\LedgerService;
use App\Domains\Financial\Services\PrizeCalculationService;
use App\Domains\Financial\Services\PrizeDistributionService;
use App\Domains\Games\Models\Game;
use App\Domains\Games\Models\GameCall;
use App\Domains\Games\Models\GameCard;
use App\Domains\Games\Services\GameLifecycleService;
use App\Domains\Patterns\Models\WinningPattern;
use App\Domains\Patterns\Services\WinningPatternService;
use App\Domains\Winners\Events\BingoClaimRejected;
use App\Domains\Winners\Events\BingoClaimSubmitted;
use App\Domains\Winners\Events\GameWon;
use App\Domains\Winners\Models\GameWinner;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class BingoVerificationService
{
    protected PrizeCalculationService $prizeCalculationService;

    protected PrizeDistributionService $prizeDistributionService;

    protected LedgerService $ledgerService;

    public function __construct(
        protected WinningPatternService $patternService,
        protected GameLifecycleService $lifecycleService,
        ?PrizeCalculationService $prizeCalculationService = null,
        ?PrizeDistributionService $prizeDistributionService = null,
        ?LedgerService $ledgerService = null
    ) {
        $this->prizeCalculationService = $prizeCalculationService ?? app(PrizeCalculationService::class);
        $this->prizeDistributionService = $prizeDistributionService ?? app(PrizeDistributionService::class);
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * Verify whether a card has completed the required winning patterns up to a specific call sequence.
     *
     * @return array{
     *     is_valid: bool,
     *     completed_count: int,
     *     required_count: int,
     *     completed_patterns: Collection<int, WinningPattern>,
     *     completed_slugs: list<string>,
     *     marked_grid: array<int, array<int, bool>>,
     *     latest_call: ?GameCall,
     *     reason: ?string
     * }
     */
    public function verifyCard(Game $game, GameCard $gameCard, ?int $upToSequence = null): array
    {
        if ($gameCard->game_id !== $game->id) {
            return [
                'is_valid' => false,
                'completed_count' => 0,
                'required_count' => 1,
                'completed_patterns' => collect(),
                'completed_slugs' => [],
                'marked_grid' => [],
                'latest_call' => null,
                'reason' => 'Card does not belong to this game.',
            ];
        }

        // Fetch numbers called in this game up to the designated sequence index
        $callsQuery = GameCall::where('game_id', $game->id)->orderBy('sequence_index');
        if ($upToSequence !== null) {
            $callsQuery->where('sequence_index', '<=', $upToSequence);
        }
        $calls = $callsQuery->get();
        $calledNumbers = $calls->pluck('ball_number')->all();
        $latestCall = $calls->last();

        if (empty($calledNumbers)) {
            return [
                'is_valid' => false,
                'completed_count' => 0,
                'required_count' => 1,
                'completed_patterns' => collect(),
                'completed_slugs' => [],
                'marked_grid' => [],
                'latest_call' => null,
                'reason' => 'No numbers have been called yet.',
            ];
        }

        // Resolve allowed patterns from configuration snapshot
        $snapshot = $game->configuration_snapshot;
        $allowedPatternIds = $snapshot['allowed_pattern_ids'] ?? [];
        $requiredCount = (int) ($snapshot['required_pattern_count'] ?? 1);
        $templateName = strtolower($snapshot['template_name'] ?? '');
        $templateSlug = strtolower($snapshot['template_slug'] ?? '');
        $patternMode = strtolower($snapshot['pattern_mode'] ?? '');

        // If line game, ensure horizontal, vertical, and diagonals are allowed
        $isLineGame = in_array($templateSlug, ['single_line', 'double_line', 'triple_line'], true)
            || str_contains($templateName, 'line')
            || $patternMode === 'single';

        if ($isLineGame) {
            $linePatternIds = WinningPattern::whereIn('type', [
                WinningPattern::TYPE_HORIZONTAL,
                WinningPattern::TYPE_VERTICAL,
                WinningPattern::TYPE_DIAGONAL,
            ])->pluck('id')->all();

            $allowedPatternIds = array_values(array_unique(array_merge($allowedPatternIds, $linePatternIds)));
        }

        $patternsQuery = WinningPattern::active();
        if (! empty($allowedPatternIds)) {
            $patternsQuery->whereIn('id', $allowedPatternIds);
        }
        $allowedPatterns = $patternsQuery->get();

        // Card grid
        $version = $gameCard->version;
        if (! $version || empty($version->grid)) {
            return [
                'is_valid' => false,
                'completed_count' => 0,
                'required_count' => $requiredCount,
                'completed_patterns' => collect(),
                'completed_slugs' => [],
                'marked_grid' => [],
                'latest_call' => $latestCall,
                'reason' => 'Card version configuration is missing or empty.',
            ];
        }

        $evaluation = $this->patternService->evaluate(
            $allowedPatterns,
            $requiredCount,
            $version->grid,
            $calledNumbers
        );

        return [
            'is_valid' => $evaluation['is_winner'],
            'completed_count' => $evaluation['completed_count'],
            'required_count' => $evaluation['required_count'],
            'completed_patterns' => $evaluation['completed_patterns'],
            'completed_slugs' => $evaluation['completed_slugs'],
            'marked_grid' => $evaluation['marked_grid'],
            'latest_call' => $latestCall,
            'reason' => $evaluation['is_winner'] ? null : "Requires {$requiredCount} pattern(s), but only {$evaluation['completed_count']} completed.",
        ];
    }

    /**
     * Authoritatively claim a Bingo win for a player and card under pessimistic concurrency control.
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     winner?: GameWinner,
     *     evaluation?: array<string, mixed>
     * }
     */
    public function claimBingo(
        Game $game,
        GameCard $gameCard,
        ?User $user = null,
        string $claimType = GameWinner::CLAIM_TYPE_MANUAL,
        bool $autoConfirm = true
    ): array {
        return DB::transaction(function () use ($game, $gameCard, $user, $claimType, $autoConfirm) {
            // Lock game record
            $lockedGame = Game::where('id', $game->id)->lockForUpdate()->first();

            if (! $lockedGame) {
                return [
                    'success' => false,
                    'message' => 'Game not found.',
                ];
            }

            if ($gameCard->game_id !== $lockedGame->id) {
                return [
                    'success' => false,
                    'message' => 'Card does not belong to this game.',
                ];
            }

            if ($gameCard->isWalkIn() || $gameCard->user_id === null) {
                $userId = null;
            } else {
                if ($user === null || $gameCard->user_id !== $user->id) {
                    return [
                        'success' => false,
                        'message' => 'Card does not belong to this player.',
                    ];
                }

                // Check if user is a participant
                if (! $lockedGame->players()->where('user_id', $user->id)->exists()) {
                    return [
                        'success' => false,
                        'message' => 'Player is not registered in this game.',
                    ];
                }

                $userId = $user->id;
            }

            // Check if card has already claimed in this game
            $alreadyClaimed = GameWinner::where('game_id', $lockedGame->id)
                ->where('game_card_id', $gameCard->id)
                ->whereIn('payout_status', [GameWinner::PAYOUT_STATUS_PENDING, GameWinner::PAYOUT_STATUS_PAID])
                ->lockForUpdate()
                ->exists();

            if ($alreadyClaimed) {
                return [
                    'success' => false,
                    'message' => 'This card has already claimed a win in this game.',
                ];
            }

            // Check if this is a simultaneous tie claim on the same ball sequence
            $isSimultaneousTie = false;
            if ($lockedGame->isCompleted() && ($lockedGame->winner_policy ?? '') === Game::WINNER_POLICY_SIMULTANEOUS) {
                $lastCallForTie = GameCall::where('game_id', $lockedGame->id)->lockForUpdate()->orderByDesc('sequence_index')->first();
                $existingWinners = GameWinner::where('game_id', $lockedGame->id)->lockForUpdate()->get();
                if ($lastCallForTie && $existingWinners->isNotEmpty() && $existingWinners->first()->winning_call_sequence === $lastCallForTie->sequence_index) {
                    $isSimultaneousTie = true;
                }
            }

            if (! $lockedGame->isActive() && ! $isSimultaneousTie) {
                return [
                    'success' => false,
                    'message' => 'Cannot claim Bingo. Game is not active.',
                ];
            }

            // Fetch the latest call made so far
            $latestCall = GameCall::where('game_id', $lockedGame->id)->lockForUpdate()->orderByDesc('sequence_index')->first();
            if (! $latestCall) {
                return [
                    'success' => false,
                    'message' => 'No numbers have been called yet.',
                ];
            }

            // Authoritative server-side pattern verification
            $evaluation = $this->verifyCard($lockedGame, $gameCard, $latestCall->sequence_index);
            if (! $evaluation['is_valid']) {
                return [
                    'success' => false,
                    'message' => $evaluation['reason'] ?? 'Invalid Bingo claim: pattern conditions not satisfied.',
                    'evaluation' => $evaluation,
                ];
            }

            // Snapshot completed patterns
            $snapshot = $evaluation['completed_patterns']->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'coordinates' => $p->coordinates,
            ])->values()->all();

            $winnerPolicy = $lockedGame->winner_policy ?? Game::WINNER_POLICY_FIRST_VALID;

            // Handle First Valid Winner Policy
            if ($winnerPolicy === Game::WINNER_POLICY_FIRST_VALID) {
                $existingWinner = GameWinner::where('game_id', $lockedGame->id)
                    ->whereIn('payout_status', [GameWinner::PAYOUT_STATUS_PENDING, GameWinner::PAYOUT_STATUS_PAID])
                    ->lockForUpdate()
                    ->first();
                if ($existingWinner) {
                    return [
                        'success' => false,
                        'message' => 'Another player has already claimed the winning Bingo for this game.',
                    ];
                }

                $prize = $this->calculatePrize($lockedGame, 1);

                $winner = GameWinner::create([
                    'company_id' => $lockedGame->company_id,
                    'game_id' => $lockedGame->id,
                    'game_card_id' => $gameCard->id,
                    'user_id' => $userId,
                    'winning_pattern_id' => $evaluation['completed_patterns']->first()?->id,
                    'winning_patterns_snapshot' => $snapshot,
                    'winning_call_sequence' => $latestCall->sequence_index,
                    'winning_ball_number' => $latestCall->ball_number,
                    'claim_type' => $claimType,
                    'payout_amount' => $prize,
                    'split_ratio' => 1.0000,
                    'payout_status' => $autoConfirm
                        ? (($userId === null) ? GameWinner::PAYOUT_STATUS_PAID : GameWinner::PAYOUT_STATUS_PENDING)
                        : GameWinner::PAYOUT_STATUS_PENDING,
                    'paid_at' => ($autoConfirm && $userId === null) ? now() : null,
                    'claimed_at' => now(),
                ]);

                if (! $autoConfirm) {
                    // Pause game immediately while host verifies the claim
                    if ($lockedGame->isActive()) {
                        $this->lifecycleService->pauseGame($lockedGame);
                    }

                    try {
                        event(new BingoClaimSubmitted($lockedGame->fresh(), $winner->fresh()));
                    } catch (\Throwable $e) {
                        Log::warning("WebSocket broadcast skipped or failed for BingoClaimSubmitted: {$e->getMessage()}");
                    }

                    return [
                        'success' => true,
                        'status' => 'pending_verification',
                        'message' => 'BINGO! Valid winning claim submitted. The game is paused while host verifies your card.',
                        'winner' => $winner->fresh(),
                        'evaluation' => $evaluation,
                    ];
                }

                // Transition game to completed
                $this->lifecycleService->completeGame($lockedGame);

                // Distribute prize via ledger
                $this->prizeDistributionService->distributeSingleWinner($winner, $lockedGame);

                // Broadcast real-time win
                $this->safeBroadcastWon($lockedGame->fresh(), $winner->fresh(), isGameCompleted: true, totalWinners: 1);

                return [
                    'success' => true,
                    'message' => 'BINGO! Valid winning claim confirmed!',
                    'winner' => $winner->fresh(),
                    'evaluation' => $evaluation,
                ];
            }

            // Handle Simultaneous Winner Policy
            if ($winnerPolicy === Game::WINNER_POLICY_SIMULTANEOUS) {
                $existingWinners = GameWinner::where('game_id', $lockedGame->id)->lockForUpdate()->get();

                if ($existingWinners->isNotEmpty()) {
                    $firstSequence = $existingWinners->first()->winning_call_sequence;

                    if ($firstSequence < $latestCall->sequence_index) {
                        return [
                            'success' => false,
                            'message' => 'Game was already completed on an earlier ball sequence.',
                        ];
                    }

                    // Tied on the exact same ball sequence!
                    $totalWinners = $existingWinners->count() + 1;
                    $splitRatio = round(1.0 / $totalWinners, 4);
                    $totalPrize = $this->calculatePrize($lockedGame, 1);
                    $splitPrize = (int) floor($totalPrize / $totalWinners);

                    // Rebalance existing winners
                    foreach ($existingWinners as $existing) {
                        if ($existing->user && $existing->payout_status === GameWinner::PAYOUT_STATUS_PAID) {
                            $diff = $existing->payout_amount - $splitPrize;
                            if ($diff > 0) {
                                $this->ledgerService->recordAdjustment(
                                    $existing->user,
                                    $diff,
                                    false,
                                    "Simultaneous tie rebalance on Game #{$lockedGame->id}"
                                );
                            }
                        }
                        $existing->update([
                            'payout_amount' => $splitPrize,
                            'split_ratio' => $splitRatio,
                        ]);
                    }

                    $winner = GameWinner::create([
                        'company_id' => $lockedGame->company_id,
                        'game_id' => $lockedGame->id,
                        'game_card_id' => $gameCard->id,
                        'user_id' => $userId,
                        'winning_pattern_id' => $evaluation['completed_patterns']->first()?->id,
                        'winning_patterns_snapshot' => $snapshot,
                        'winning_call_sequence' => $latestCall->sequence_index,
                        'winning_ball_number' => $latestCall->ball_number,
                        'claim_type' => $claimType,
                        'payout_amount' => $splitPrize,
                        'split_ratio' => $splitRatio,
                        'payout_status' => ($userId === null) ? GameWinner::PAYOUT_STATUS_PAID : GameWinner::PAYOUT_STATUS_PENDING,
                        'paid_at' => ($userId === null) ? now() : null,
                        'claimed_at' => now(),
                    ]);

                    if ($lockedGame->isActive()) {
                        $this->lifecycleService->completeGame($lockedGame);
                    }

                    // Distribute prize via ledger
                    $this->prizeDistributionService->distributeSingleWinner($winner, $lockedGame);

                    $this->safeBroadcastWon($lockedGame->fresh(), $winner->fresh(), isGameCompleted: true, totalWinners: $totalWinners);

                    return [
                        'success' => true,
                        'message' => "BINGO! Simultaneous tie claim confirmed! Prize split {$totalWinners} ways.",
                        'winner' => $winner->fresh(),
                        'evaluation' => $evaluation,
                    ];
                }

                // First winner in simultaneous mode
                $prize = $this->calculatePrize($lockedGame, 1);

                $winner = GameWinner::create([
                    'company_id' => $lockedGame->company_id,
                    'game_id' => $lockedGame->id,
                    'game_card_id' => $gameCard->id,
                    'user_id' => $userId,
                    'winning_pattern_id' => $evaluation['completed_patterns']->first()?->id,
                    'winning_patterns_snapshot' => $snapshot,
                    'winning_call_sequence' => $latestCall->sequence_index,
                    'winning_ball_number' => $latestCall->ball_number,
                    'claim_type' => $claimType,
                    'payout_amount' => $prize,
                    'split_ratio' => 1.0000,
                    'payout_status' => ($userId === null) ? GameWinner::PAYOUT_STATUS_PAID : GameWinner::PAYOUT_STATUS_PENDING,
                    'paid_at' => ($userId === null) ? now() : null,
                    'claimed_at' => now(),
                ]);

                // Transition game to completed
                $this->lifecycleService->completeGame($lockedGame);

                // Distribute prize via ledger
                $this->prizeDistributionService->distributeSingleWinner($winner, $lockedGame);

                $this->safeBroadcastWon($lockedGame->fresh(), $winner->fresh(), isGameCompleted: true, totalWinners: 1);

                return [
                    'success' => true,
                    'message' => 'BINGO! Valid winning claim confirmed!',
                    'winner' => $winner->fresh(),
                    'evaluation' => $evaluation,
                ];
            }

            throw new InvalidArgumentException("Unsupported winner policy: {$winnerPolicy}");
        });
    }

    /**
     * Check and award automatic Bingo winners for all participating cards upon a ball draw.
     *
     * @return Collection<int, GameWinner>
     */
    public function checkAutomaticWinners(Game $game, GameCall $call): Collection
    {
        $cards = GameCard::with(['version', 'user'])
            ->where('game_id', $game->id)
            ->get();

        $winners = collect();

        foreach ($cards as $card) {
            $eval = $this->verifyCard($game, $card, $call->sequence_index);
            if ($eval['is_valid']) {
                $claimResult = $this->claimBingo($game, $card, $card->user, GameWinner::CLAIM_TYPE_AUTOMATIC);
                if ($claimResult['success'] && isset($claimResult['winner'])) {
                    $winners->push($claimResult['winner']);

                    if (($game->winner_policy ?? '') === Game::WINNER_POLICY_FIRST_VALID) {
                        break;
                    }
                }
            }
        }

        return $winners;
    }

    /**
     * Authoritatively approve and confirm a pending winning claim by company admin, releasing payout and finalizing the game.
     *
     * @return array{success: bool, message: string, winner?: GameWinner}
     */
    public function confirmWinnerClaim(Game $game, GameWinner $winner): array
    {
        return DB::transaction(function () use ($game, $winner) {
            $lockedGame = Game::where('id', $game->id)->lockForUpdate()->firstOrFail();
            $lockedWinner = GameWinner::where('id', $winner->id)->lockForUpdate()->firstOrFail();

            if ($lockedWinner->game_id !== $lockedGame->id) {
                return [
                    'success' => false,
                    'message' => 'Claim does not belong to this game.',
                ];
            }

            if ($lockedWinner->payout_status === GameWinner::PAYOUT_STATUS_PAID) {
                return [
                    'success' => false,
                    'message' => 'This winning claim has already been approved and paid.',
                ];
            }

            // Distribute prize
            if ($lockedWinner->isWalkIn() || $lockedWinner->user_id === null) {
                $lockedWinner->update([
                    'payout_status' => GameWinner::PAYOUT_STATUS_PAID,
                    'paid_at' => now(),
                ]);
            } else {
                $this->prizeDistributionService->distributeSingleWinner($lockedWinner, $lockedGame);
            }

            // Transition game to completed if not already completed
            if (! $lockedGame->isCompleted()) {
                $this->lifecycleService->completeGame($lockedGame);
            }

            // Broadcast real-time win
            $this->safeBroadcastWon($lockedGame->fresh(), $lockedWinner->fresh(), isGameCompleted: true, totalWinners: 1);

            return [
                'success' => true,
                'message' => 'Winning claim confirmed and game finalized successfully!',
                'winner' => $lockedWinner->fresh(),
            ];
        });
    }

    /**
     * Authoritatively reject a false Bingo claim by company admin, allowing session to resume.
     *
     * @return array{success: bool, message: string, winner?: GameWinner}
     */
    public function rejectWinnerClaim(Game $game, GameWinner $winner, ?string $reason = null): array
    {
        return DB::transaction(function () use ($game, $winner, $reason) {
            $lockedGame = Game::where('id', $game->id)->lockForUpdate()->firstOrFail();
            $lockedWinner = GameWinner::where('id', $winner->id)->lockForUpdate()->firstOrFail();

            if ($lockedWinner->game_id !== $lockedGame->id) {
                return [
                    'success' => false,
                    'message' => 'Claim does not belong to this game.',
                ];
            }

            $lockedWinner->update([
                'payout_status' => GameWinner::PAYOUT_STATUS_REJECTED,
            ]);

            try {
                event(new BingoClaimRejected($lockedGame->fresh(), $lockedWinner->fresh(), $reason));
            } catch (\Throwable $e) {
                Log::warning("WebSocket broadcast skipped or failed for BingoClaimRejected: {$e->getMessage()}");
            }

            return [
                'success' => true,
                'message' => 'Bingo claim rejected. You may now resume the game session.',
                'winner' => $lockedWinner->fresh(),
            ];
        });
    }

    /**
     * Check and declare a walk-in player as winner and finalize game.
     *
     * @return array{success: bool, message: string, winner?: GameWinner, evaluation?: array<string, mixed>}
     */
    public function declareWalkInWinner(Game $game, GameCard $gameCard): array
    {
        return DB::transaction(function () use ($game, $gameCard) {
            $lockedGame = Game::where('id', $game->id)->lockForUpdate()->firstOrFail();

            if ($gameCard->game_id !== $lockedGame->id) {
                return [
                    'success' => false,
                    'message' => 'Card does not belong to this game.',
                ];
            }

            $latestCall = GameCall::where('game_id', $lockedGame->id)->lockForUpdate()->orderByDesc('sequence_index')->first();
            if (! $latestCall) {
                return [
                    'success' => false,
                    'message' => 'No numbers have been called yet.',
                ];
            }

            $evaluation = $this->verifyCard($lockedGame, $gameCard, $latestCall->sequence_index);
            if (! $evaluation['is_valid']) {
                return [
                    'success' => false,
                    'message' => $evaluation['reason'] ?? 'Invalid Bingo claim: pattern conditions not satisfied.',
                    'evaluation' => $evaluation,
                ];
            }

            $existingWinner = GameWinner::where('game_id', $lockedGame->id)
                ->where('payout_status', GameWinner::PAYOUT_STATUS_PAID)
                ->lockForUpdate()
                ->first();

            if ($existingWinner) {
                return [
                    'success' => false,
                    'message' => 'A winner has already been confirmed for this game.',
                ];
            }

            $snapshot = $evaluation['completed_patterns']->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'coordinates' => $p->coordinates,
            ])->values()->all();

            $prize = $this->calculatePrize($lockedGame, 1);

            $winner = GameWinner::create([
                'company_id' => $lockedGame->company_id,
                'game_id' => $lockedGame->id,
                'game_card_id' => $gameCard->id,
                'user_id' => null,
                'winning_pattern_id' => $evaluation['completed_patterns']->first()?->id,
                'winning_patterns_snapshot' => $snapshot,
                'winning_call_sequence' => $latestCall->sequence_index,
                'winning_ball_number' => $latestCall->ball_number,
                'claim_type' => GameWinner::CLAIM_TYPE_MANUAL,
                'payout_amount' => $prize,
                'split_ratio' => 1.0000,
                'payout_status' => GameWinner::PAYOUT_STATUS_PAID,
                'paid_at' => now(),
                'claimed_at' => now(),
            ]);

            // Complete game
            $this->lifecycleService->completeGame($lockedGame);

            // Broadcast real-time win
            $this->safeBroadcastWon($lockedGame->fresh(), $winner->fresh(), isGameCompleted: true, totalWinners: 1);

            return [
                'success' => true,
                'message' => "Walk-in winner confirmed! Payout: {$winner->formattedPayout()}",
                'winner' => $winner->fresh(),
                'evaluation' => $evaluation,
            ];
        });
    }

    /**
     * Calculate individual prize amount for a game based on prize configuration and winner count.
     */
    public function calculatePrize(Game $game, int $winnerCount = 1): int
    {
        return $this->prizeCalculationService->calculatePrizeForPosition($game, 1, $winnerCount);
    }

    /**
     * Safely dispatch GameWon event with resilient error handling.
     */
    protected function safeBroadcastWon(Game $game, GameWinner $winner, bool $isGameCompleted, int $totalWinners): void
    {
        try {
            event(new GameWon($game, $winner, isGameCompleted: $isGameCompleted, totalWinners: $totalWinners));
        } catch (\Throwable $e) {
            Log::warning("WebSocket broadcast skipped or failed for GameWon: {$e->getMessage()}");
        }
    }
}
