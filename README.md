# Nobingo — Multi-Company 75-Ball Fixed-Card Bingo Platform

A high-performance, production-quality **75-ball multiplayer Bingo platform** designed for SaaS / multi-company rental, built on Laravel 12/13, React, Inertia.js, TypeScript, and Tailwind CSS.

---

## Key Principles & Invariants

1. **Fixed Bingo Cards**: Cards are permanently generated inventory assets stored in the database. Cards are never dynamically created when a game starts.
2. **Multi-Company Tenancy**: A single server installation supports multiple rented companies. Each company operates in its own isolated scope (`company_id`) with its own fixed-card inventory, game rooms, player accounts, and prize ledgers.
3. **Platform Owner Oversight**: A global Platform Owner (`PLATFORM_OWNER`) controls tenant provisioning, quotas, and cross-company monitoring.
4. **Server is the Authoritative Source of Truth**: The client browser is strictly a display terminal. The server authoritatively verifies cards, calls numbers using cryptographically secure random sequences, validates pattern completions, and verifies Bingo claims under strict database row locks.

---

## Role-Based Access Control (RBAC)

| Role | Target Portal | Primary Responsibilities |
|---|---|---|
| **`PLATFORM_OWNER`** | `/platform/dashboard` | Super Admin / App Owner: Provisions companies, manages platform quotas, monitors global system metrics. |
| **`COMPANY_ADMIN`** | `/c/{company}/admin` | Company Tenant Admin: Manages company fixed card inventory, game templates, scheduled rooms, players, and ledgers. |
| **`GAME_MANAGER`** | `/c/{company}/admin` | Bingo Caller / Operator: Conducts live bingo calling sessions and inspects claims. |
| **`PLAYER`** | `/c/{company}/dashboard` | Bingo Player: Joins games, plays assigned fixed cards, manages wallet balance, and submits Bingo claims. |

---

## Development Setup

### Requirements
- PHP 8.3+ (PHP 8.5 active)
- Composer 2.9+
- Node.js 22+ / npm 11+
- MySQL 8.0+ running on port 3306

### Quickstart Commands
```powershell
# 1. Install PHP dependencies
composer install

# 2. Install Node dependencies
npm install

# 3. Configure environment
# Ensure .env has:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=nobingo
# DB_USERNAME=root
# DB_PASSWORD=1234

# 4. Run migrations and seed data
php artisan migrate:fresh --seed

# 5. Build frontend assets
npm run build

# 6. Start development server
php artisan serve
```

### Default Seeded Test Accounts
All seeded accounts use password: `Password123!`

- **Platform Owner**: `owner@nobingo.test`
- **Acme Bingo Club Admin**: `admin@acme.test`
- **Acme Bingo Club Player**: `player1@acme.test`
- **Lucky Star Gaming Admin**: `admin@luckystar.test`
- **Lucky Star Gaming Player**: `player2@luckystar.test`

---

## Running Automated Tests
```powershell
php artisan test
```
All feature and unit tests run in-memory using SQLite for sub-second execution speed while the application itself connects to MySQL.
