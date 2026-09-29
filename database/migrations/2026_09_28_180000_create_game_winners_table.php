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
        Schema::create('game_winners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('game_card_id')->constrained('game_cards')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('winning_pattern_id')->nullable()->constrained('winning_patterns')->nullOnDelete();
            $table->json('winning_patterns_snapshot');
            $table->unsignedTinyInteger('winning_call_sequence');
            $table->unsignedTinyInteger('winning_ball_number');
            $table->string('claim_type')->default('manual'); // manual, automatic
            $table->unsignedBigInteger('payout_amount')->default(0);
            $table->decimal('split_ratio', 5, 4)->default(1.0000);
            $table->string('payout_status')->default('pending'); // pending, paid, rejected
            $table->timestamp('claimed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['game_id', 'game_card_id']);
            $table->index(['company_id', 'game_id']);
            $table->index(['user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_winners');
    }
};
