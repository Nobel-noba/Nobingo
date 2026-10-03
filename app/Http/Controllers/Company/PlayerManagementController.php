<?php

namespace App\Http\Controllers\Company;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Audit\Services\AuditLogger;
use App\Domains\Auth\Models\Role;
use App\Domains\Financial\Models\Transaction;
use App\Domains\Financial\Services\LedgerService;
use App\Domains\Games\Models\GamePlayer;
use App\Domains\Tenancy\Models\Company;
use App\Domains\Tenancy\Models\GameManagerPlayer;
use App\Domains\Winners\Models\GameWinner;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class PlayerManagementController extends Controller
{
    public function __construct(
        protected LedgerService $ledgerService,
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Display a paginated directory of registered company players.
     */
    public function index(Request $request, Company $company): Response
    {
        $user = $request->user();
        $isGameManagerOnly = $user->isGameManager() && ! $user->isCompanyAdmin() && ! $user->isPlatformOwner();
        $search = $request->query('search');
        $status = $request->query('status');

        if ($isGameManagerOnly) {
            // Game Manager view: only players on their roster
            $gmpQuery = GameManagerPlayer::with('player')
                ->where('company_id', $company->id)
                ->where('game_manager_id', $user->id)
                ->latest('id');

            if ($search) {
                $gmpQuery->whereHas('player', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($status && in_array($status, ['active', 'suspended'], true)) {
                $gmpQuery->whereHas('player', fn ($q) => $q->where('status', $status));
            }

            $players = $gmpQuery->paginate(15)->withQueryString()->through(function (GameManagerPlayer $gmp) use ($company, $user) {
                $player = $gmp->player;
                $winCount = GameWinner::where('company_id', $company->id)
                    ->where('user_id', $player->id)
                    ->whereHas('game', fn ($g) => $g->where('created_by', $user->id))
                    ->count();
                $gamesCount = GamePlayer::whereHas('game', fn ($g) => $g->where('company_id', $company->id)->where('created_by', $user->id))
                    ->where('user_id', $player->id)
                    ->count();

                return [
                    'id' => $player->id,
                    'name' => $player->name,
                    'email' => $player->email,
                    'status' => $player->status ?? 'active',
                    'balance' => $gmp->balance,
                    'formatted_balance' => $gmp->formattedBalance(),
                    'win_count' => $winCount,
                    'games_count' => $gamesCount,
                    'created_at' => $gmp->created_at->format('M d, Y'),
                ];
            });

            // List of company players not yet in this manager's roster
            $existingIds = GameManagerPlayer::where('game_manager_id', $user->id)->pluck('player_id');
            $availablePlayers = User::where('company_id', $company->id)
                ->whereHas('roles', fn ($q) => $q->where('slug', Role::PLAYER))
                ->whereNotIn('id', $existingIds)
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
        } else {
            // Admin view: all company players
            $query = User::where('company_id', $company->id)
                ->whereHas('roles', fn ($q) => $q->where('slug', Role::PLAYER))
                ->withCount(['transactions'])
                ->latest('id');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($status && in_array($status, ['active', 'suspended'], true)) {
                $query->where('status', $status);
            }

            $players = $query->paginate(15)->withQueryString()->through(function (User $user) use ($company) {
                $winCount = GameWinner::where('company_id', $company->id)->where('user_id', $user->id)->count();
                $gamesCount = GamePlayer::whereHas('game', fn ($g) => $g->where('company_id', $company->id))
                    ->where('user_id', $user->id)
                    ->count();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status ?? 'active',
                    'balance' => $user->balance,
                    'formatted_balance' => $user->formattedBalance(),
                    'win_count' => $winCount,
                    'games_count' => $gamesCount,
                    'created_at' => $user->created_at->format('M d, Y'),
                ];
            });

            $availablePlayers = [];
        }

        return Inertia::render('Company/Players/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'players' => $players,
            'available_players' => $availablePlayers,
            'is_game_manager_view' => $isGameManagerOnly,
            'filters' => [
                'search' => $search ?? '',
                'status' => $status ?? 'all',
            ],
        ]);
    }

    /**
     * Display detailed dossier for an individual player.
     */
    public function show(Company $company, User $player): Response
    {
        if ((int) $player->company_id !== (int) $company->id) {
            abort(404, 'Player does not belong to this company.');
        }

        // Recent Games
        $recentGames = GamePlayer::where('user_id', $player->id)
            ->whereHas('game', fn ($q) => $q->where('company_id', $company->id))
            ->with('game:id,game_number,name,status,entry_fee,created_at')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn ($gp) => [
                'game_id' => $gp->game->id,
                'game_number' => $gp->game->game_number,
                'name' => $gp->game->name,
                'status' => $gp->game->status,
                'entry_fee_paid' => $gp->entry_fee_paid,
                'joined_at' => $gp->joined_at ? $gp->joined_at->format('M d, Y H:i') : '-',
            ]);

        // Wins
        $wins = GameWinner::where('company_id', $company->id)
            ->where('user_id', $player->id)
            ->with(['game:id,game_number,name', 'pattern:id,name'])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn ($w) => [
                'id' => $w->id,
                'game_number' => $w->game->game_number,
                'game_name' => $w->game->name,
                'pattern_name' => $w->pattern->name ?? 'Standard',
                'payout_amount' => $w->payout_amount,
                'formatted_payout' => '$'.number_format($w->payout_amount / 100, 2),
                'payout_status' => $w->payout_status,
                'claimed_at' => $w->claimed_at->format('M d, Y H:i'),
            ]);

        // Recent Transactions
        $transactions = Transaction::where('company_id', $company->id)
            ->where('user_id', $player->id)
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn ($tx) => [
                'id' => $tx->id,
                'type' => $tx->type,
                'amount' => $tx->amount,
                'formatted_amount' => $tx->formattedAmount(),
                'balance_after' => $tx->balance_after,
                'formatted_balance_after' => $tx->formattedBalanceAfter(),
                'reference_code' => $tx->reference_code,
                'description' => $tx->description,
                'created_at' => $tx->created_at->format('M d, Y H:i'),
            ]);

        return Inertia::render('Company/Players/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'player' => [
                'id' => $player->id,
                'name' => $player->name,
                'email' => $player->email,
                'status' => $player->status ?? 'active',
                'balance' => $player->balance,
                'formatted_balance' => $player->formattedBalance(),
                'created_at' => $player->created_at->format('M d, Y'),
            ],
            'recent_games' => $recentGames,
            'wins' => $wins,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Suspend or activate a player account.
     */
    public function toggleStatus(Request $request, Company $company, User $player): RedirectResponse
    {
        if ((int) $player->company_id !== (int) $company->id) {
            abort(404);
        }

        $user = $request->user();
        if ($user && $user->isGameManager() && ! $user->isCompanyAdmin() && ! $user->isPlatformOwner()) {
            abort(403, 'Unauthorized. Game managers cannot suspend or activate players. Only administrators can perform this action.');
        }

        $newStatus = ($player->status ?? 'active') === 'active' ? 'suspended' : 'active';
        $player->update(['status' => $newStatus]);

        $action = $newStatus === 'suspended' ? AuditLog::ACTION_PLAYER_SUSPENDED : AuditLog::ACTION_PLAYER_ACTIVATED;
        $this->auditLogger->log(
            $action,
            $player,
            "Operator toggled status of player {$player->name} to {$newStatus}.",
            ['new_status' => $newStatus]
        );

        return back()->with('success', "Player {$player->name} has been {$newStatus}.");
    }

    /**
     * Perform an administrative balance adjustment on a player's wallet.
     */
    public function adjustBalance(Request $request, Company $company, User $player): RedirectResponse
    {
        if ((int) $player->company_id !== (int) $company->id) {
            abort(404);
        }

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:50000'], // in dollars
            'is_credit' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $amountInCents = $validated['amount'] * 100;
        $isCredit = (bool) $validated['is_credit'];
        $reason = $validated['reason'];

        try {
            $tx = $this->ledgerService->recordAdjustment($player, $amountInCents, $isCredit, $reason);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->auditLogger->log(
            AuditLog::ACTION_BALANCE_ADJUSTED,
            $tx,
            "Admin adjusted balance for {$player->name}: ".($isCredit ? '+' : '-')."\${$validated['amount']}.00. Reason: {$reason}",
            [
                'player_id' => $player->id,
                'amount' => $amountInCents,
                'is_credit' => $isCredit,
                'reason' => $reason,
            ]
        );

        return back()->with('success', "Wallet balance for {$player->name} updated successfully.");
    }

    /**
     * Register a new player account by Game Manager or Company Admin.
     */
    public function store(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'max:100'],
            'initial_deposit' => ['nullable', 'numeric', 'min:0', 'max:50000'],
        ]);

        $player = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'company_id' => $company->id,
            'status' => 'active',
            'balance' => 0,
            'must_reset_password' => true,
        ]);

        $playerRole = Role::firstOrCreate(
            ['slug' => Role::PLAYER],
            ['name' => 'Player', 'description' => 'Bingo player participating in live game sessions.']
        );
        $player->roles()->sync([$playerRole->id]);

        $user = $request->user();
        $managerId = $user->isGameManager() ? $user->id : null;

        if ($managerId !== null) {
            GameManagerPlayer::create([
                'company_id' => $company->id,
                'game_manager_id' => $managerId,
                'player_id' => $player->id,
                'balance' => 0,
            ]);
        }

        $initialDeposit = (float) ($validated['initial_deposit'] ?? 0);
        if ($initialDeposit > 0) {
            $amountInCents = (int) round($initialDeposit * 100);
            $this->ledgerService->recordDeposit(
                $player,
                $amountInCents,
                referenceCode: 'INIT-'.bin2hex(random_bytes(4)),
                description: 'Initial deposit upon counter registration',
                managerId: $managerId
            );
        }

        $this->auditLogger->log(
            AuditLog::ACTION_PLAYER_ACTIVATED,
            $player,
            "Operator registered new player {$player->name} ({$player->email}) with temporary password.",
            [
                'player_id' => $player->id,
                'manager_id' => $managerId,
                'initial_deposit' => $initialDeposit,
                'must_reset_password' => true,
            ]
        );

        return back()->with('success', "Player {$player->name} successfully registered. They will be prompted to set a new password upon first login.");
    }

    /**
     * Authorize an existing company player on the current Game Manager's roster.
     */
    public function authorizeExisting(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'player_id' => ['required', 'exists:users,id'],
            'initial_deposit' => ['nullable', 'numeric', 'min:0', 'max:50000'],
        ]);

        /** @var User $player */
        $player = User::where('id', $validated['player_id'])
            ->where('company_id', $company->id)
            ->firstOrFail();

        $user = $request->user();
        $managerId = $user->id;

        $existing = GameManagerPlayer::where('game_manager_id', $managerId)
            ->where('player_id', $player->id)
            ->first();

        if ($existing) {
            return back()->with('error', "Player {$player->name} is already authorized on your roster.");
        }

        GameManagerPlayer::create([
            'company_id' => $company->id,
            'game_manager_id' => $managerId,
            'player_id' => $player->id,
            'balance' => 0,
        ]);

        $initialDeposit = (float) ($validated['initial_deposit'] ?? 0);
        if ($initialDeposit > 0) {
            $amountInCents = (int) round($initialDeposit * 100);
            $this->ledgerService->recordDeposit(
                $player,
                $amountInCents,
                referenceCode: 'MGR-AUTH-'.bin2hex(random_bytes(4)),
                description: "Initial deposit upon authorization by {$user->name}",
                managerId: $managerId
            );
        }

        $this->auditLogger->log(
            AuditLog::ACTION_PLAYER_ACTIVATED,
            $player,
            "Operator {$user->name} authorized player {$player->name} to participate in their games.",
            [
                'player_id' => $player->id,
                'manager_id' => $managerId,
                'initial_deposit' => $initialDeposit,
            ]
        );

        return back()->with('success', "Player {$player->name} successfully added to your game roster.");
    }

    /**
     * Process a counter cash withdrawal for a player.
     */
    public function withdraw(Request $request, Company $company, User $player): RedirectResponse
    {
        if ((int) $player->company_id !== (int) $company->id) {
            abort(404);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:50000'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $amountInCents = (int) round(((float) $validated['amount']) * 100);
        $user = $request->user();
        $managerId = $user->isGameManager() ? $user->id : null;
        $reason = $validated['notes'] ?? 'Counter cash withdrawal';

        try {
            $tx = $this->ledgerService->recordWithdrawal(
                $player,
                $amountInCents,
                referenceCode: 'WTH-'.bin2hex(random_bytes(5)),
                description: "Counter cash withdrawal via {$user->name}: {$reason}",
                managerId: $managerId
            );
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->auditLogger->log(
            AuditLog::ACTION_BALANCE_ADJUSTED,
            $tx,
            "Operator {$user->name} processed withdrawal of \${$validated['amount']}.00 for {$player->name}.",
            [
                'player_id' => $player->id,
                'manager_id' => $managerId,
                'amount' => $amountInCents,
                'reason' => $reason,
            ]
        );

        return back()->with('success', "Successfully processed withdrawal of \${$validated['amount']}.00 for {$player->name}.");
    }

    /**
     * Reset a player's password to a temporary password by Game Manager or Company Admin.
     */
    public function resetPassword(Request $request, Company $company, User $player): RedirectResponse
    {
        if ((int) $player->company_id !== (int) $company->id) {
            abort(404);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:6', 'max:100'],
        ]);

        $player->forceFill([
            'password' => Hash::make($validated['password']),
            'must_reset_password' => true,
        ])->save();

        $this->auditLogger->log(
            AuditLog::ACTION_PLAYER_ACTIVATED,
            $player,
            "Operator reset password for player {$player->name} ({$player->email}).",
            [
                'player_id' => $player->id,
                'must_reset_password' => true,
            ]
        );

        return back()->with('success', "Temporary password set for {$player->name}. They must change it upon their next login.");
    }
}
