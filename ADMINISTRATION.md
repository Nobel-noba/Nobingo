# Phase 9: Administration & Reporting Engine

## 1. Overview
Phase 9 delivers an enterprise-grade administration, moderation, reporting, and forensic audit subsystem for the Nobingo multi-tenant platform. Tenant operators (Company Admins and Game Managers) have complete operational visibility over players, templates, games, finances, and immutable audit logs within their isolated domain. Platform Owners maintain multi-tenant governance.

---

## 2. Architectural Components

### 2.1 Audit Logging Domain (`App\Domains\Audit`)
- **`AuditLog` Model**: Immutable record storing tenant (`company_id`), acting operator (`user_id`), action constant, polymorphic subject (`auditable_type`, `auditable_id`), human description, and JSON context details (`details`).
- **`AuditLogger` Service**: Injected singleton service providing standardized structured event capture:
  ```php
  $this->auditLogger->log(
      AuditLog::ACTION_PLAYER_SUSPENDED,
      $player,
      "Operator toggled status of player {$player->name} to suspended.",
      ['new_status' => 'suspended']
  );
  ```
- **Audited Events**:
  - `PLAYER_SUSPENDED`, `PLAYER_ACTIVATED`
  - `BALANCE_ADJUSTED` (tied to transaction record)
  - `TEMPLATE_CREATED`, `TEMPLATE_TOGGLED`
  - `GAME_CREATED`, `GAME_STARTED`, `GAME_CANCELLED`
  - `CARD_BATCH_GENERATED`
  - `WINNER_VERIFIED`

### 2.2 Player Management & Moderation
- **Player Directory** (`/c/{company}/admin/players`):
  - Paginated directory of registered tenant players.
  - Search by player name or email.
  - Filter by account status (`active` / `suspended`).
  - Displays wallet balance, total games played, and total bingo wins.
- **Player Dossier** (`/c/{company}/admin/players/{player}`):
  - Summary profile with balance and tenure.
  - Recent participation history with room number, status, and entry fees.
  - Win history with verified patterns, payout amounts, and claim timestamps.
  - Full double-entry financial ledger transaction feed.
- **Account Moderation** (`PATCH /c/{company}/admin/players/{player}/toggle-status`):
  - Toggles between `active` and `suspended`.
  - Suspended accounts cannot join games or submit claims.
  - Produces immutable audit log entry.
- **Administrative Wallet Adjustments** (`POST /c/{company}/admin/players/{player}/adjust-balance`):
  - Credit or debit player balance with mandatory audit justification note.
  - Invokes `LedgerService::recordAdjustment()`.
  - Prevents negative balances on debit (`DomainException` caught safely).
  - Creates both a `TYPE_ADJUSTMENT` ledger transaction and a `BALANCE_ADJUSTED` audit log.

### 2.3 Game Templates (`App\Domains\Games\Models\GameTemplate`)
- Reusable game presets for rapid room creation.
- Tenant admins can author custom blueprints or reuse global system presets:
  - Pattern modes (`single_pattern`, `multi_pattern`, `progressive`)
  - Target pattern selection and required pattern counts
  - Winner policies (`first_valid`, `simultaneous`)
  - Call interval, player capacity, entry fees, and prize structure
- Toggling active status safely scopes to tenant templates (`403 Forbidden` if attempting to modify foreign tenant templates).

### 2.4 Executive Analytics & Reporting (`App\Domains\Reports\Services\ReportingService`)
- Aggregate metrics calculated dynamically across database partitions:
  - **Game Performance**: Total games, completed games, cancelled games, active rooms, completion rate %, and average calls required to win.
  - **Financial Metrics**: Gross entry fees collected, prizes paid, refunds issued, deposits, withdrawals, net house earnings, and house margin %.
  - **Player KPIs**: Total registered players, active players, suspended players, and top 5 winners leaderboard with total prize earnings and win counts.

### 2.5 Section 49 Game Replay & Forensic Verification Inspector
- Accessible from `/c/{company}/admin/games/{game}/audit` and game management views.
- **Chronological Ball Call Feed**: Ordered list of all called balls with sequence index, call timestamp, and ball badge.
- **Participating Card Inspector**: Lists all enrolled players and their 5x5 fixed bingo cards. Modal card viewer enables interactive inspection of numbers and daub marks.
- **Winning Pattern Breakdown & Mathematical Explanation**: For each winner, breaks down why the claim was legitimate:
  - Winning call sequence number
  - Target winning pattern
  - Matched coordinate cells `[col, row]`
  - Payout amount and distribution status
- **Game Audit Event Stream**: Filtered audit logs specifically related to this game session.

---

## 3. Strict Multi-Tenant Isolation
- All administrative routes enforce `'tenant'` and `'role:PLATFORM_OWNER,COMPANY_ADMIN,GAME_MANAGER'` middleware.
- Scoped route binding ensures operators of Company A cannot view or manipulate players, templates, audit logs, or games belonging to Company B (`404 Not Found` or `403 Forbidden`).
- Regular players attempting to access any admin endpoints receive `403 Forbidden`.

---

## 4. Verification Suite
The entire Phase 9 administration suite is verified under automated tests:
- `tests/Feature/AdminManagementTest.php` (12 tests, 170 assertions, 100% passing)
- Full application suite: **126 tests, 842 assertions, 0 failures**
- Frontend assets compiled with TypeScript and Vite.
