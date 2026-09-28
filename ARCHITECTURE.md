# Nobingo System Architecture

## Architectural Principles

### 1. Domain Separation
Business and game logic are decoupled from HTTP controllers into dedicated domains under `app/Domains/`:
- `Domains/Tenancy/`: Company models, tenant context singleton, `BelongsToCompany` trait, and `CompanyScope`.
- `Domains/Auth/`: Role and Permission models, `HasRoles` trait, RBAC middleware.
- `Domains/Cards/`: (Phase 2) Fixed-card generator, validator, deterministic hashing, and inventory.
- `Domains/Patterns/`: (Phase 3) 5x5 coordinate geometry, pattern counting, and win configuration.
- `Domains/Games/`: (Phase 4) Game lifecycle state machine, templates, and card assignment.
- `Domains/Calling/`: (Phase 5) Cryptographic 1–75 number generator and persistence.
- `Domains/Winners/`: (Phase 7) Verification engine, automatic detection, and race condition protection.
- `Domains/Financial/`: (Phase 8) Double-entry ledger and prize calculation.
- `Domains/Audit/`: (Phase 9) Immutable game event replay and compliance logs.

### 2. Multi-Company Tenancy (Row-Level Scoping)
- Single database architecture with `companies` table.
- All tenant entities (`users`, `bingo_cards`, `games`, `prizes`, `transactions`) reference `company_id`.
- The global `CompanyScope` ensures that Company A's queries automatically filter by `where company_id = ?`, rendering cross-company data leakage impossible.
- The `PLATFORM_OWNER` user operates across company boundaries for administration and oversight.

### 3. Server Authority
- Card numbers are generated on the server and hashed deterministically (`card_hash`).
- Balls (1–75) are drawn using server-side cryptographically secure pseudorandom number generators.
- Player cards are marked deterministically based on server called numbers:
  $$\text{Marked Cells} = \text{Card Numbers} \cap \text{Called Numbers} \cup \{\text{FREE Center}\}$$
- The browser cannot mark cells that were not drawn or submit fabricated cards.
- Winning claims require transactional verification and database locks to prevent double-claiming or inconsistent state.
