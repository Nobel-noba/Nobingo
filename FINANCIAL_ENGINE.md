# Financial Engine, Double-Entry Ledger & Prize Payouts (Phase 8)

The Financial Engine provides server-authoritative wallet balance management, immutable audit logging with double-entry principles, pluggable real-money payment gateway abstraction, and an automated prize calculation and distribution pipeline.

---

## 1. Architectural Highlights

- **Server-Authoritative Balances**: User wallet balances (`users.balance` stored as integer cents) cannot be updated directly outside of transactional boundaries. All balance mutations must pass through `LedgerService`.
- **Double-Entry Balance Invariant**: Every balance mutation produces an immutable record in `transactions` where:
  - Credits (`DEPOSIT`, `PRIZE`, `REFUND`): `balance_after === balance_before + amount`
  - Debits (`ENTRY_FEE`, `WITHDRAWAL`): `balance_after === balance_before - amount`
- **Pessimistic Concurrency**: All balance transactions acquire a row-level database lock on the user model (`lockForUpdate()`) within a database transaction (`DB::transaction`).
- **Strict Idempotency**:
  - Entry fee deductions check for existing `ENTRY_FEE` transaction for that game and user.
  - Prize payouts check for existing `PRIZE` transaction for that `GameWinner` reference.
  - Cancellation refunds check for existing `REFUND` transaction for that game and user.
- **Tenant Isolation**: Every financial transaction is explicitly scoped to `company_id`. Company administrators can only view transactions originating within their tenancy.
- **Pluggable Payment Gateway**:
  - `PaymentGatewayInterface` abstracts external payment systems (Stripe, PayPal, banking rails).
  - `SandboxPaymentGateway` provides development and sandbox payment processing.

---

## 2. Database Schema

### `transactions` Table
| Column | Type | Description |
|---|---|---|
| `id` | `BIGINT UNSIGNED` (PK) | Unique transaction ID |
| `company_id` | `BIGINT UNSIGNED` (FK) | Scoped tenant company (`companies.id`) |
| `user_id` | `BIGINT UNSIGNED` (FK) | Participating player or operator account (`users.id`) |
| `type` | `VARCHAR(32)` | `DEPOSIT`, `WITHDRAWAL`, `ENTRY_FEE`, `PRIZE`, `REFUND`, `ADJUSTMENT` |
| `amount` | `BIGINT UNSIGNED` | Transaction amount in cents (e.g., 5000 = $50.00) |
| `currency` | `VARCHAR(3)` | Default `'USD'` |
| `status` | `VARCHAR(32)` | `'pending'`, `'completed'`, `'failed'` |
| `balance_before` | `BIGINT UNSIGNED` | User wallet balance before transaction |
| `balance_after` | `BIGINT UNSIGNED` | User wallet balance after transaction |
| `reference_type` | `VARCHAR(255)` (Nullable) | Polymorphic model class (e.g. `App\Domains\Games\Models\Game`) |
| `reference_id` | `BIGINT UNSIGNED` (Nullable) | Polymorphic model ID |
| `reference_code` | `VARCHAR(64)` (Nullable, Indexed) | Unique human-readable reference code |
| `description` | `VARCHAR(255)` (Nullable) | Contextual description |
| `created_at` / `updated_at` | `TIMESTAMP` | Timestamps |

---

## 3. Core Services & Interfaces

### `LedgerService`
Located at `app/Domains/Financial/Services/LedgerService.php`.
- `recordEntryFee(User $user, Game $game): Transaction`: Validates player wallet, checks idempotency, locks user, debits entry fee, creates `ENTRY_FEE` transaction, and broadcasts `TransactionCreated`.
- `recordPrizePayout(GameWinner $winner): Transaction`: Credits winner balance, marks `GameWinner::payout_status = 'paid'`, records `PRIZE` transaction, and broadcasts `TransactionCreated`.
- `recordRefund(User $user, Game $game, int $amount, ?string $reason): Transaction`: Refunds entry fee when games are cancelled.
- `recordDeposit(User $user, int $amount, ?string $refCode, ?string $desc): Transaction`: Credits user wallet from payment gateway.
- `recordWithdrawal(User $user, int $amount, ?string $refCode, ?string $desc): Transaction`: Debits user wallet for cashouts after verifying sufficient balance.
- `recordAdjustment(User $user, int $amount, bool $isCredit, string $reason): Transaction`: Administrative corrections and tie-breaker rebalancing.

### `PrizeCalculationService`
Located at `app/Domains/Financial/Services/PrizeCalculationService.php`.
- `calculateTotalPrizePool(Game $game): int`:
  - **Fixed Prize**: Returns `fixed_prize` specified in game configuration.
  - **Percentage Pool**: Computes `entry_fee * players * (pot_percentage / 100)`.
  - **Guaranteed Fallback**: 80% of entry fee pool, or minimum guaranteed pot of $100.00.
- `calculatePrizeForPosition(Game $game, int $position = 1, int $winnersInPosition = 1): int`:
  - Resolves position percentage share from `positions` or `tier_percentages` configuration.
  - Computes equal split among tied co-winners on the exact same ball call: `floor($positionPool / $winnersInPosition)`.

### `PrizeDistributionService`
Located at `app/Domains/Financial/Services/PrizeDistributionService.php`.
- `distributeSingleWinner(GameWinner $winner, ?Game $game): Transaction`: Atomically executes prize payout via `LedgerService` and fires `PrizeDistributed` event.
- `distributePrizesForGame(Game $game): Collection<GameWinner>`: Automatically scans pending winners of a completed game and executes payouts.

### `PaymentGatewayInterface` & `SandboxPaymentGateway`
Located at `app/Domains/Financial/Contracts/PaymentGatewayInterface.php` and `app/Domains/Financial/Services/Payment/SandboxPaymentGateway.php`.
- Provides dependency injection binding for wallet funding and cashout flows.

---

## 4. Engine Integrations

1. **Card Assignment Engine (`CardAssignmentService::joinGame`)**:
   - Replaced manual balance decrement with `LedgerService::recordEntryFee($user, $game)`.
   - Players cannot join a paid room without sufficient wallet funds.
2. **Winner Claim Engine (`BingoVerificationService::claimBingo`)**:
   - Replaced raw calculation with `PrizeCalculationService`.
   - Upon confirming a valid winning Bingo claim, `PrizeDistributionService::distributeSingleWinner` is immediately triggered to credit the winner's wallet.
   - For simultaneous ties on the exact same ball sequence, existing winner records are rebalanced, adjustments are logged, and co-winners receive equal split payouts.
3. **Game Lifecycle Engine (`GameLifecycleService::cancelGame`)**:
   - When a room is cancelled, all registered players who paid entry fees automatically receive full `REFUND` transactions back to their wallet.

---

## 5. User Interfaces & Routes

### Company Admin Treasury & Ledger
- **URL**: `/c/{company:slug}/admin/ledger`
- **Route**: `company.admin.ledger.index`
- **View**: `resources/js/Pages/Company/Ledger/Index.tsx`
- **Features**:
  - Live Treasury Summary: Entry fees collected, prizes paid, refunds issued, net house earnings.
  - Search and Type Filters (`ALL`, `ENTRY_FEE`, `PRIZE`, `REFUND`, `DEPOSIT`, `WITHDRAWAL`, `ADJUSTMENT`).
  - Double-entry audit table with balance deltas, timestamps, player details, and reference codes.

### Player Wallet & Cashier
- **URL**: `/c/{company:slug}/wallet`
- **Routes**:
  - `GET /c/{company:slug}/wallet` (`player.wallet.index`)
  - `POST /c/{company:slug}/wallet/deposit` (`player.wallet.deposit`)
  - `POST /c/{company:slug}/wallet/withdraw` (`player.wallet.withdraw`)
- **View**: `resources/js/Pages/Player/Wallet.tsx`
- **Features**:
  - Live Wallet Balance Card with quick deposit buttons ($10, $25, $50, $100, $250).
  - Sandbox Withdrawal Drawer with boundary validation (minimum $1.00, maximum available balance).
  - Comprehensive transaction history with status badges and balance progression.
  - Header Wallet Balance pill is interactive and links directly to this wallet view.
