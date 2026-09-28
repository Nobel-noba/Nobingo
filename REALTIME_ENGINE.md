# Phase 6 — Real-Time Engine (WebSockets & Laravel Reverb)

## 1. Overview
The Real-Time Engine delivers instantaneous, low-latency state synchronization across all connected clients (players, callers, room operators, and spectators) without polling. It is powered by:
- **Laravel Reverb**: First-party, blazing-fast WebSocket server built directly for Laravel.
- **Client Integration**: `laravel-echo` paired with `pusher-js` configured for secure, tenant-isolated private channels.
- **Server Authority**: Only the server evaluates game logic and dispatches broadcast events (`ShouldBroadcastNow`). Clients never dictate or trigger game state changes directly over WebSockets.
- **Tenant Isolation**: Channel authorization rules strictly enforce multi-tenant boundaries. A player or admin from Company A can never subscribe to or listen in on Company B's game channels or lobby events.

---

## 2. Broadcast Events

All broadcast events implement `ShouldBroadcastNow` to ensure real-time immediate delivery without waiting for background queue workers:

### `NumberCalled` (`App\Domains\Games\Events\NumberCalled`)
- **Fired When**: A new number is drawn by the caller or automated loop.
- **Channel**: `private-company.{companyId}.game.{gameId}`
- **Event Name**: `number.called`
- **Payload**:
  ```json
  {
    "game_id": 1,
    "sequence_index": 5,
    "ball_number": 24,
    "letter": "I",
    "code": "I-24",
    "called_at": "2026-09-28T17:30:00.000000Z",
    "remaining_count": 70,
    "marked_cards_count": 3
  }
  ```

### `GameStateChanged` (`App\Domains\Games\Events\GameStateChanged`)
- **Fired When**: Game status changes (e.g. `open` -> `locked` -> `active` -> `completed` -> `cancelled`).
- **Channels**:
  - `private-company.{companyId}.game.{gameId}` (for active room participants and caller dashboard)
  - `private-company.{companyId}.lobby` (for users browsing available rooms)
- **Event Name**: `game.state.changed`
- **Payload**:
  ```json
  {
    "game_id": 1,
    "code": "GAME-0001",
    "status": "active",
    "previous_status": "locked",
    "started_at": "2026-09-28T17:30:00.000000Z",
    "ended_at": null,
    "current_pattern_index": 0
  }
  ```

### `PlayerJoinedGame` (`App\Domains\Games\Events\PlayerJoinedGame`)
- **Fired When**: A player joins a game and purchases/is assigned cards.
- **Channels**:
  - `private-company.{companyId}.game.{gameId}`
  - `private-company.{companyId}.lobby`
- **Event Name**: `player.joined`
- **Payload**:
  ```json
  {
    "game_id": 1,
    "user_id": 4,
    "player_name": "Alice Smith",
    "players_count": 8
  }
  ```

---

## 3. Channel Authorization Rules (`routes/channels.php`)

Private channel security is enforced at the framework level:

```php
Broadcast::channel('company.{companyId}.game.{gameId}', function (User $user, $companyId, $gameId) {
    if ($user->isPlatformOwner()) {
        return true;
    }

    if ((int) $user->company_id !== (int) $companyId) {
        return false;
    }

    if ($user->hasRole(Role::COMPANY_ADMIN) || $user->hasRole(Role::GAME_MANAGER)) {
        return true;
    }

    // Verify game belongs to this company
    return Game::where('id', $gameId)
        ->where('company_id', $companyId)
        ->exists();
});

Broadcast::channel('company.{companyId}.lobby', function (User $user, $companyId) {
    if ($user->isPlatformOwner()) {
        return true;
    }

    return (int) $user->company_id === (int) $companyId;
});
```

---

## 4. Frontend Client Architecture (`resources/js/echo.ts`)

A unified singleton Echo instance is initialized:

```typescript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo<'reverb'>;
    }
}

window.Pusher = Pusher;

export const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

### Integrated React Views:
1. **`GameRoom.tsx`** (`resources/js/Pages/Player/GameRoom.tsx`):
   - Subscribes to `private-company.${company.id}.game.${game.id}`.
   - Listens to `.number.called`: instantly animates latest ball, plays audio cue, updates master caller board, and marks matching numbers on user's active cards.
   - Listens to `.game.state.changed`: updates room state banner and enables/disables interactions.
   - Listens to `.player.joined`: updates live player count.
2. **`Show.tsx`** (`resources/js/Pages/CompanyAdmin/Games/Show.tsx`):
   - Operator Caller Dashboard with real-time ball feed, sequence log, and remaining pool count.
3. **`Lobby.tsx`** (`resources/js/Pages/Player/Lobby.tsx`):
   - Subscribes to `private-company.${company.id}.lobby`.
   - Listens to `.game.state.changed` and `.player.joined` to update room cards, participant counters, and availability in real time.

---

## 5. Verification & Test Suite

The test suite (`tests/Feature/RealTimeEngineTest.php`) covers:
1. `test_number_called_event_implements_broadcasting_channels_and_payload`
2. `test_game_state_changed_event_broadcasts_to_game_and_lobby_channels`
3. `test_player_joined_event_broadcasts_to_game_and_lobby_channels`
4. `test_lifecycle_transition_dispatches_game_state_changed_event`
5. `test_joining_game_dispatches_player_joined_game_event`
6. `test_calling_number_dispatches_number_called_event`
7. `test_channel_authorization_enforces_tenant_and_game_isolation` (Multi-tenant private channel authorization, cross-tenant rejection, and platform owner override)

Total test count across all phases: **90 passing tests, 522 assertions, 0 failures**.
