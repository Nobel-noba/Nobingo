# Phase 4 — Game Engine, Lifecycle & Room Management

## 1. Overview
The Game Engine orchestrates room lifecycles, configuration snapshots, and atomic card assignments for the multi-tenant fixed-card 75-Ball Bingo platform. In strict accordance with the architecture:
- Games are bound to a specific tenant (`Company`).
- Cards are permanent fixed database assets drawn from the company's verified inventory.
- Game rules and winning patterns are permanently frozen into a `configuration_snapshot` JSON when a game is created from a template, guaranteeing historical reproducibility and zero runtime mutation.
- Player registrations and card allocations occur under database row-level locks (`SELECT ... FOR UPDATE`) to prevent race conditions and duplicate card claims.

---

## 2. Game Lifecycle & State Machine

```
              ┌─────────┐
              │  DRAFT  │
              └────┬────┘
                   │ open()
                   ▼
              ┌─────────┐
       ┌─────►│  OPEN   ├─────┐
       │      └────┬────┘     │
resume()           │ start()  │
       │           ▼          │
  ┌────┴────┐ ┌─────────┐     │
  │ PAUSED  │ │STARTING │     │
  └────▲────┘ └────┬────┘     │
       │ pause()   │ activate() cancel()
       └─────┐     ▼          │
          ┌──┴──────────┐     │
          │   ACTIVE    │     │
          └──┬──────────┘     │
             │ complete()     │
             ▼                ▼
       ┌───────────┐    ┌───────────┐
       │ COMPLETED │    │ CANCELLED │
       └───────────┘    └───────────┘
```

### State Definitions
| State | Description | Allowed Actions |
|---|---|---|
| `DRAFT` | Initial setup state. Template rules configured. | `open`, `cancel` |
| `OPEN` | Room is open in player lobby. Players can join and purchase cards. | `start`, `cancel` |
| `STARTING` | Countdown/warmup state before ball calling begins. | `activate`, `cancel` |
| `ACTIVE` | Balls are being called. Bingo claims can be evaluated. | `pause`, `complete`, `cancel` |
| `PAUSED` | Calling temporarily paused (e.g. claim review, technical pause). | `resume`, `cancel` |
| `COMPLETED` | Game completed, winners confirmed, prizes distributed, cards released. | *Terminal* |
| `CANCELLED` | Game aborted, fees refunded, cards released. | *Terminal* |

---

## 3. Configuration Snapshot Architecture

When a game is created from a `GameTemplate`, the system creates a frozen `configuration_snapshot` containing:
- `template_id` and template metadata
- `pattern_type` (`single_line`, `multi_line`, `pattern`, `blackout`)
- `pattern_ids` (exact array of `winning_patterns` IDs required)
- `rules` (call interval, max cards per player, max total cards, auto-daub, claims policy)
- `pricing` (card entry fee, platform fee percentage, house cut percentage, prize distribution profile)

**Why snapshots matter:**
If an administrator updates or deletes a `GameTemplate` or `WinningPattern` in the future, active and historical games remain 100% deterministic and unaffected.

---

## 4. Atomic Card Assignment Engine

Fixed cards are pre-generated permanent physical/digital assets. When a player registers for a game room:
1. **Concurrency Lock**: A transaction is opened and existing allocations are locked via `lockForUpdate()`.
2. **Quota Checks**:
   - Room total card capacity (`max_cards`) is enforced.
   - Player card quota (`max_cards_per_player`) is enforced.
3. **Availability Verification**:
   - Cards already assigned in this specific game cannot be selected again.
   - If random assignment is requested, available fixed cards from the company's verified inventory (`status = 'available'`) are claimed atomically.
4. **State Transition**:
   - `game_players` record is created or updated.
   - `game_cards` records are inserted with initial `marked_positions` (`[[2,2]]` for FREE square).
   - Global card asset status transitions from `'available'` to `'in_use'`.
5. **Release on Game End**:
   - When a game transitions to `COMPLETED` or `CANCELLED`, all linked fixed cards atomically revert to `'available'`.

---

## 5. Multi-Company Tenancy Enforcement

All game queries and operations are strictly partitioned by `company_id`:
- Company A can only view, host, and control Company A games.
- Players registered to Company A can only view Company A's lobby and join Company A's game rooms.
- Company A games can only pull cards from Company A's fixed inventory.

---

## 6. Verification & Automated Testing

Phase 4 is fully verified by `tests/Feature/GameEngineTest.php`:
- `test_company_admin_can_create_game_from_template_with_snapshot`
- `test_game_lifecycle_transitions_and_validations`
- `test_player_can_join_game_and_acquire_random_fixed_cards`
- `test_player_cannot_exceed_max_cards_per_player`
- `test_cannot_assign_same_card_twice_in_same_game`
- `test_game_cards_are_released_back_to_available_when_game_completes`
- `test_game_cards_are_released_back_to_available_when_game_is_cancelled`
- `test_tenant_isolation_prevents_cross_company_game_access`

Total suite: **73 passing tests** (214 assertions).
