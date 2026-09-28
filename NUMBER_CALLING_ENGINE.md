# Phase 5 — Number Calling Engine & Server-Authoritative Dauber

## 1. Overview
The Number Calling Engine is the core drawing authority of the 75-Ball Fixed-Card Bingo platform. It enforces:
- **Server Authority**: Random numbers are generated strictly on the server using cryptographically secure pseudorandom number generators (CSPRNG, `random_int`).
- **Zero Repetition Guarantee**: Balls are drawn without replacement from the remaining set of uncalled numbers (1 through 75).
- **Physical Database Safeguards**: Unique composite constraints on `[game_id, sequence_index]` and `[game_id, ball_number]` guarantee that no number can ever be called twice in the same game, and sequence indexes remain strictly consecutive.
- **Server-Side Card Daubing**: Automatically marks called coordinates on all participating fixed cards and authoritatively validates manual player daubing requests to prevent client manipulation.

---

## 2. 75-Ball Column Standard

| Letter | Column Range | Valid Coordinates (Col Index) |
|---|---|---|
| **B** | 1 – 15 | Col 0 |
| **I** | 16 – 30 | Col 1 |
| **N** | 31 – 45 | Col 2 *(Center cell `[2, 2]` is FREE)* |
| **G** | 46 – 60 | Col 3 |
| **O** | 61 – 75 | Col 4 |

---

## 3. Database Schema

### `game_calls` Table
```sql
CREATE TABLE game_calls (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    game_id BIGINT UNSIGNED NOT NULL,
    sequence_index TINYINT UNSIGNED NOT NULL,   -- 1 to 75
    ball_number TINYINT UNSIGNED NOT NULL,      -- 1 to 75
    letter CHAR(1) NOT NULL,                    -- 'B', 'I', 'N', 'G', 'O'
    called_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    UNIQUE KEY (game_id, sequence_index),
    UNIQUE KEY (game_id, ball_number),
    INDEX (game_id, sequence_index)
);
```

### `game_cards` Table Addition
- `marked_positions`: JSON array storing the array of marked coordinates `[[row, col], ...]`.
- Initialized with `[[2, 2]]` for the center `FREE` square.

---

## 4. Services & Calling Flow

### `NumberCallingService` (`app/Domains/Games/Services/NumberCallingService.php`)
- `callNextNumber(Game $game): ?GameCall`:
  1. Validates that `$game->isActive()`.
  2. Acquires row-level locks on the game and existing calls inside a database transaction.
  3. Computes remaining pool: `array_diff(range(1, 75), $calledNumbers)`.
  4. Returns `null` if all 75 numbers are drawn.
  5. Cryptographically draws `$pickedNumber = $remaining[random_int(0, count($remaining) - 1)]`.
  6. Maps to letter (`B`, `I`, `N`, `G`, `O`) and stores into `game_calls`.
  7. Invokes `autoDaubCardsForNumber()` to mark cells on all cards holding this number.
  8. Dispatches `NumberCalled` event.
- `manualDaub(GameCard $gameCard, int $row, int $col): bool`:
  1. Validates coordinate bounds (0..4).
  2. If `[2, 2]`, marks and returns `true` (FREE square).
  3. Checks cell number on card's version grid.
  4. Checks if cell number was actually called in this game. If not called, throws `InvalidArgumentException`.
  5. If called, saves coordinate to `game_cards.marked_positions`.
- `getMasterBoard(Game $game)`: Returns 1–75 numbers partitioned by B-I-N-G-O with `is_called`, `sequence_index`, and `called_at` for high-end caller boards.

---

## 5. Artisan Commands

- **Single Draw**:
  ```bash
  php artisan bingo:call {game_id}
  ```
- **Automated Loop**:
  ```bash
  php artisan bingo:call-loop {game_id} --interval=4 --max-calls=75
  ```

---

## 6. HTTP API & Routes

- `POST /c/{company}/admin/games/{game}/call-next`: Operator manual call button.
- `GET /c/{company}/game/{game}/state`: Live game and caller polling state.
- `POST /c/{company}/game/{game}/cards/{gameCard}/daub`: Player cell daubing with server validation.

---

## 7. Verification & Automated Tests

Phase 5 is verified by `tests/Feature/NumberCallingEngineTest.php`:
- `test_calling_numbers_requires_active_game_status`
- `test_calling_generates_valid_letter_and_sequential_index`
- `test_calling_all_75_numbers_produces_no_duplicates_and_exact_consecutive_sequences`
- `test_database_unique_constraints_prevent_duplicate_calls`
- `test_auto_daub_updates_card_marked_positions_when_matching_number_is_called`
- `test_manual_daub_validates_cell_against_called_numbers`
- `test_manual_daub_succeeds_for_called_numbers_and_free_square`
- `test_operator_can_call_next_number_via_api`
- `test_player_can_fetch_live_game_state_and_daub_via_http`
- `test_tenant_isolation_game_calls_do_not_leak_across_companies`

Total test suite: **83 passed tests** (487 assertions).
