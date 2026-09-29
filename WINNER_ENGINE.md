# Phase 7 — Winner Engine & Bingo Claim Verification

## 1. Overview
The Winner Engine is the authoritative referee and auditing layer of the 75-Ball Fixed-Card Bingo platform. Under no circumstances does the server trust client-side claims or marked grids:
- **Server Authority**: Every Bingo claim is independently and strictly verified against the database log of called balls (`game_calls`), the immutable card number grid (`bingo_card_versions`), and data-driven pattern geometry (`winning_patterns`).
- **Pessimistic Concurrency & Race-Condition Locking**: Database transactions with `lockForUpdate()` on the game and winner records guarantee that simultaneous claims never create contradictory or corrupted winner states.
- **Winner Policy Support**:
  - `first_valid`: The very first verified claim wins the prize and immediately completes the game.
  - `simultaneous`: Multiple players reaching Bingo on the exact same ball sequence are recognized as co-winners, and the prize is automatically split according to the calculated split ratio.
- **Real-Time Notification**: Instant broadcast of `GameWon` over Laravel Reverb to active room participants and tenant lobbies.
- **Immutable Winner Record**: Stores permanent snapshots of the completed pattern coordinates, winning ball, sequence index, payout amounts, and claim methods in `game_winners`.

---

## 2. Database Schema (`game_winners` Table)

```sql
CREATE TABLE game_winners (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    game_id BIGINT UNSIGNED NOT NULL,
    game_card_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    winning_pattern_id BIGINT UNSIGNED NULL,
    winning_patterns_snapshot JSON NOT NULL,
    winning_call_sequence TINYINT UNSIGNED NOT NULL,
    winning_ball_number TINYINT UNSIGNED NOT NULL,
    claim_type VARCHAR(255) NOT NULL DEFAULT 'manual', -- manual, automatic
    payout_amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
    split_ratio DECIMAL(5, 4) NOT NULL DEFAULT 1.0000,
    payout_status VARCHAR(255) NOT NULL DEFAULT 'pending',
    claimed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (game_card_id) REFERENCES game_cards(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (winning_pattern_id) REFERENCES winning_patterns(id) ON DELETE SET NULL,
    UNIQUE KEY (game_id, game_card_id),
    INDEX (company_id, game_id),
    INDEX (user_id)
);
```

---

## 3. Verification Workflow (`BingoVerificationService`)

When a claim is evaluated via `claimBingo(Game $game, GameCard $gameCard, User $user, string $claimType)`:
1. **Transaction & Row Locking**:
   - Acquires row-level pessimistic lock on `games` via `lockForUpdate()`.
2. **Invariant Checks**:
   - Verifies the card belongs to the requested game.
   - Verifies the user is the owner of the card and a registered participant in `game_players`.
   - Checks if the card has already claimed in this game (`unique(['game_id', 'game_card_id'])`).
   - Verifies game is currently `ACTIVE` (or is a valid simultaneous tie on the current winning ball).
3. **Number & Pattern Verification**:
   - Loads the latest call from `game_calls` (`orderByDesc('sequence_index')`).
   - Retrieves allowed patterns from `configuration_snapshot['allowed_pattern_ids']`.
   - Runs `WinningPatternService::evaluate(...)` comparing card grid cells against balls called up to the designated sequence index.
   - Requires completed patterns count $\ge$ `required_pattern_count`.
4. **Winner Policy Resolution**:
   - If `first_valid`: Verifies no previous winner exists, creates `GameWinner` with 100% prize, transitions game to `COMPLETED`, releases cards, and dispatches `GameWon`.
   - If `simultaneous`: Checks if previous winners exist on the current ball sequence. If so, updates all existing winners on that ball sequence with adjusted split ratios (e.g. 50% for 2 winners, 33.3% for 3 winners) and adds the new winner.
5. **Automatic Detection Support**:
   - `checkAutomaticWinners(Game $game, GameCall $call)` scans active cards upon each ball draw and automatically triggers claims if `auto_claim` is enabled.

---

## 4. Real-Time Broadcast Event (`GameWon`)

Broadcasts on private channels:
- `company.{company_id}.game.{game_id}`
- `company.{company_id}.lobby`

Payload structure:
```json
{
  "game_id": 1,
  "game_number": 1001,
  "winner_id": 4,
  "winner_name": "Alice Smith",
  "card_id": 12,
  "card_number": "#000247",
  "patterns": ["Row 1 (Horizontal)"],
  "winning_ball": "I-24",
  "winning_call_sequence": 24,
  "winning_ball_number": 24,
  "payout_amount": 5000,
  "formatted_payout": "$50.00",
  "claim_type": "manual",
  "is_game_completed": true,
  "total_winners": 1
}
```

---

## 5. UI & Player Experience

1. **Player Game Room (`GameRoom.tsx`)**:
   - Prominent **BINGO!** button pulsing when the game is active.
   - Client submits `POST /c/{company}/game/{game}/cards/{gameCard}/claim-bingo`.
   - Echo listener for `.game.won` triggers celebratory modal with winner announcement, confetti styling, winning ball, pattern details, and payout.
2. **Company Admin Winners Ledger (`Company/Winners/Index.tsx`)**:
   - Real-time ledger of all winners across games with KPI stats (total winners, payouts, manual vs automatic claims).
   - Filtering by game and search by player name/email.
3. **Operator Game Center (`Company/Games/Show.tsx`)**:
   - Live **Verified Winners Banner** displaying card details, winning ball, patterns, and payouts.
4. **Player Dashboard (`Player/Dashboard.tsx`)**:
   - Displays real-time `games_won` count.

---

## 6. Verification Results

### Automated Test Suite (`php artisan test`)
- Full test suite passed: **101 tests, 573 assertions, 0 failures**.
- Feature test suite [`WinnerEngineTest.php`](file:///c:/Users/Noba/Documents/Workspace/Nobingo/tests/Feature/WinnerEngineTest.php) covers:
  1. `test_server_verifies_valid_bingo_claim_for_called_numbers`
  2. `test_server_rejects_bingo_claim_if_card_pattern_not_complete`
  3. `test_server_rejects_claim_from_non_card_owner_or_non_participant`
  4. `test_server_rejects_claim_on_inactive_or_completed_game`
  5. `test_first_valid_winner_policy_completes_game_and_blocks_subsequent_claims`
  6. `test_simultaneous_winner_policy_splits_prize_on_same_ball_sequence`
  7. `test_pessimistic_locking_prevents_duplicate_winner_records_for_same_card`
  8. `test_game_won_event_broadcasts_on_game_and_lobby_channels`
  9. `test_automatic_bingo_detection_claims_win_upon_ball_call`
  10. `test_player_can_submit_bingo_claim_via_http_endpoint`
  11. `test_multi_tenancy_isolation_prevents_cross_tenant_winner_access`

### Code Quality & Standards (`vendor/bin/pint`)
- 100% clean formatting adhering to Laravel Boost rules.

### Frontend Compilation (`npm run build`)
- 100% clean TypeScript and Vite production bundle.
