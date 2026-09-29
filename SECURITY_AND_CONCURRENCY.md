# Phase 10: Security, Concurrency, Anti-Cheat & Financial Integrity Testing

## 1. Overview
Phase 10 subjects the Nobingo 75-Ball Fixed-Card multi-tenant platform to comprehensive adversarial testing, concurrency simulation, race condition validation, anti-cheat hardening, and financial ledger conservation verification.

---

## 2. Core Security & Concurrency Safeguards

### 2.1 Multi-Tenant Isolation & Authorization Boundary
- **Row-Level Scoping**: All games, cards, calls, claims, and financial records belong to a specific tenant (`company_id`).
- **Route Model Binding Validation**:
  - Operators of Company A cannot view or manipulate Company B's players, templates, audit logs, or games (`404 Not Found` or `403 Forbidden`).
  - Players belonging to Company A cannot view or join games belonging to Company B.
- **Card Ownership Enforcing**:
  - Players cannot submit Bingo claims on cards assigned to other players (`422 Unprocessable Content`).
  - Players cannot submit daubs on cards assigned to other players (`403 Forbidden`).
- **RBAC Privilege Escalation Prevention**:
  - Regular players are barred from all `/c/{company}/admin/*` routes with `403 Forbidden`.

### 2.2 Suspended Account Policy & Hardening
- Suspended player accounts (`status: 'suspended'`) are strictly blocked by server-side guards:
  - **Game Entry**: Prevented in `CardAssignmentService::joinGame()` with `InvalidArgumentException`.
  - **Bingo Claims**: Prevented in `BingoClaimController::claim()` with `403 Forbidden`.
  - **Wallet Withdrawals**: Prevented in `WalletController::withdraw()` with validation errors.

### 2.3 Production-Grade Rate Limiting
Configured in `AppServiceProvider` and enforced on sensitive player routes:
- **`bingo.claim`**: 30 claims / min per user/IP. Prevents script-based brute-force claiming and claim flooding.
- **`bingo.daub`**: 120 daubs / min per user/IP. Prevents automated coordinate hammering.
- **`wallet.operations`**: 20 operations / min per user/IP. Prevents deposit/withdrawal transaction flooding.
- Exceeding any threshold immediately returns HTTP `429 Too Many Requests`.

### 2.4 Server-Authoritative Anti-Cheat & Game Manipulation Protection
- **No Client Trust for Bingo Evaluation**:
  - Bingo claims are calculated entirely on the server using `BingoVerificationService`.
  - Marked cells are computed deterministically as $\text{Card Numbers} \cap \text{Called Numbers} \cup \{\text{FREE}\}$.
  - Submitting claims with incomplete patterns fails verification (`422 Unprocessable Content`).
- **Manual Daub Validation**:
  - Daubs on numbers not yet drawn by the server are rejected.
  - Coordinates outside `0..4, 0..4` are rejected with `422 Unprocessable Content`.
- **Game Lifecycle Boundary Guards**:
  - Cannot join games in `draft`, `starting`, `active`, `paused`, `completed`, or `cancelled` states.
  - Cannot exceed configured `max_players` capacity.
  - Cannot join the same game twice with the same player account.

### 2.5 Concurrency & Race Condition Hardening
- **Simultaneous Bingo Claims on the Same Ball**:
  - **`first_valid` Policy**: Uses database pessimistic locking (`lockForUpdate`). The first verified claim declares a winner and transitions the game to `completed`. Subsequent claims are rejected with `"Cannot claim Bingo. Game is not active."`. Exactly one winner is awarded.
  - **`simultaneous` Policy**: Multiple claims occurring on the exact same ball sequence number are recognized as tied co-winners. The total prize pool is split evenly, with existing payouts rebalanced in real time via ledger adjustments.
- **Single-Card Inventory Contention**:
  - When only 1 card remains in company inventory, parallel join attempts are protected by atomic database transactions. Exactly one player is assigned the card, while the second is safely rejected (`RuntimeException: No available cards in company inventory for game assignment`).
- **Double-Spend & Rapid Withdrawal Protection**:
  - Parallel withdrawal requests lock the user record using `lockForUpdate()`.
  - Balance is checked atomically. If balance is insufficient, `RuntimeException` is thrown.
  - Account balances can never drop below zero.

### 2.6 Financial Integrity & Zero-Leakage Conservation
- **Double-Entry Ledger Invariant**:
  $$\text{Initial Balance} + \sum \text{Credits} - \sum \text{Debits} = \text{Current Balance}$$
  Verified mathematically across deposits, entry fees, adjustments, withdrawals, and prize payouts.
- **Zero-Remainder Prize Splitting**:
  In odd splits (e.g. \$100.00 across 3 winners), odd remainder cents are mathematically accounted for:
  $$\text{Winner 1: } \$33.34, \quad \text{Winner 2: } \$33.33, \quad \text{Winner 3: } \$33.33 \implies \sum = \$100.00$$

---

## 3. Automated Verification Results
- **`tests/Feature/SecurityAuditTest.php`**: **13/13 tests passed, 41 assertions**.
- **`tests/Feature/ConcurrencyTest.php`**: **6/6 tests passed, 31 assertions**.
- **Platform Total**: **145 tests passed, 914 assertions, 0 failures (100% green)**.
- **Laravel Pint Code Formatter**: Clean.
- **TypeScript & Vite Asset Bundling**: Clean.
