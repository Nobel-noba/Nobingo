<?php

use App\Domains\Auth\Models\Role;
use App\Domains\Games\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Tenant Game Room Channel.
 * Broadcasts ball calls, game lifecycle changes, and in-room player updates.
 */
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

/**
 * Tenant Lobby Channel.
 * Broadcasts room creation, player counts, and room state transitions.
 */
Broadcast::channel('company.{companyId}.lobby', function (User $user, $companyId) {
    if ($user->isPlatformOwner()) {
        return true;
    }

    return (int) $user->company_id === (int) $companyId;
});
