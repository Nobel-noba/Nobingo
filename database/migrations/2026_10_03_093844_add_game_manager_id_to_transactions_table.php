<?php

use App\Domains\Games\Models\Game;
use App\Domains\Winners\Models\GameWinner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('game_manager_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->index(['company_id', 'game_manager_id']);
        });

        // Backfill game_manager_id for existing transactions in MySQL production
        if (DB::getDriverName() === 'mysql') {
            DB::statement('
                UPDATE transactions
                INNER JOIN games ON transactions.reference_id = games.id AND transactions.reference_type = ?
                SET transactions.game_manager_id = games.created_by
                WHERE games.created_by IS NOT NULL
            ', [Game::class]);

            DB::statement('
                UPDATE transactions
                INNER JOIN game_winners ON transactions.reference_id = game_winners.id AND transactions.reference_type = ?
                INNER JOIN games ON game_winners.game_id = games.id
                SET transactions.game_manager_id = games.created_by
                WHERE games.created_by IS NOT NULL
            ', [GameWinner::class]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['game_manager_id']);
            $table->dropIndex(['company_id', 'game_manager_id']);
            $table->dropColumn('game_manager_id');
        });
    }
};
