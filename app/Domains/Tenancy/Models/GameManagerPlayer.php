<?php

namespace App\Domains\Tenancy\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameManagerPlayer extends Model
{
    use HasFactory;

    protected $table = 'game_manager_players';

    protected $fillable = [
        'company_id',
        'game_manager_id',
        'player_id',
        'balance',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function gameManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'game_manager_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_id');
    }

    public function formattedBalance(): string
    {
        return '$'.number_format($this->balance / 100, 2);
    }
}
