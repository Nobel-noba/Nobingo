<?php

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
        Schema::create('game_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence_index');
            $table->unsignedTinyInteger('ball_number');
            $table->char('letter', 1);
            $table->timestamp('called_at')->useCurrent();
            $table->timestamps();

            $table->unique(['game_id', 'sequence_index']);
            $table->unique(['game_id', 'ball_number']);
            $table->index(['game_id', 'sequence_index']);
        });

        Schema::table('game_cards', function (Blueprint $table) {
            $table->json('marked_positions')->nullable()->after('bingo_card_version_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_cards', function (Blueprint $table) {
            $table->dropColumn('marked_positions');
        });

        Schema::dropIfExists('game_calls');
    }
};
