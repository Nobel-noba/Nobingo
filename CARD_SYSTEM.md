# Nobingo Fixed Card System

## 1. Core Principle: Persistent Fixed Cards

Unlike conventional bingo apps that randomly construct a temporary card whenever a game room starts, Nobingo maintains a **permanent database inventory of fixed cards**.

1. **Permanent Assets**: A card (e.g., `Card #000247`) is generated once and stored permanently in the database.
2. **Deterministic Fingerprint (`card_hash`)**: Each card layout has a canonical SHA-256 hash. The database enforces a uniqueness constraint `UNIQUE(company_id, card_hash)` ensuring no two identical cards can exist within the same company inventory.
3. **Card Versioning**: If numbers ever need to be adjusted for administrative or maintenance reasons, a new `BingoCardVersion` record is created. Historical completed games continue to point permanently to the exact version used during that match.
4. **Card Reusability**: When a game concludes, cards are released according to the company's reuse policy. The card numbers themselves remain permanent and unchanged.

---

## 2. 75-Ball Column Specifications

A card is a 5×5 matrix conforming to strict 75-ball bingo standards:

| Column | Ball Range | Cell Count | Rules |
|---|---|---|---|
| **B** | 1 – 15 | 5 | Unique integers in range [1, 15] |
| **I** | 16 – 30 | 5 | Unique integers in range [16, 30] |
| **N** | 31 – 45 | 4 (+ FREE) | Center position `[2, 2]` is automatically marked `FREE` (value `0`) |
| **G** | 46 – 60 | 5 | Unique integers in range [46, 60] |
| **O** | 61 – 75 | 5 | Unique integers in range [61, 75] |

Total playable distinct numbers: **24**. No duplicate numbers are permitted on a single card.

---

## 3. Database Schema

### `bingo_cards`
- `id` (bigint PK)
- `company_id` (foreign key -> `companies`)
- `card_number` (unsigned int, sequential per company: 1, 2, 3...)
- `status` (`available`, `reserved`, `assigned`, `in_use`, `locked`, `retired`, `disabled`)
- `current_version_id` (foreign key -> `bingo_card_versions`)
- `card_hash` (varchar 64, SHA-256)
- Unique constraints: `(company_id, card_number)`, `(company_id, card_hash)`

### `bingo_card_versions`
- `id` (bigint PK)
- `bingo_card_id` (foreign key -> `bingo_cards`)
- `version_number` (unsigned int: 1, 2, 3...)
- `card_hash` (varchar 64)
- `grid` (JSON 5×5 array of numbers)
- `b_column`, `i_column`, `n_column`, `g_column`, `o_column` (JSON column projections)
- `created_by` (foreign key -> `users`, nullable)
- `notes` (text, nullable)

---

## 4. Batch Generation Command

Admins can generate batches of fixed cards from the command line:
```powershell
php artisan bingo:cards:generate {company_slug} {count=100}
```
Or via the Company Admin UI at `/c/{company_slug}/admin/cards`.
