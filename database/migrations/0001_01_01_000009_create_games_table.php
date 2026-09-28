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
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_template_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('game_number')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->json('configuration_snapshot');
            $table->unsignedBigInteger('entry_fee')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('min_players')->default(1);
            $table->unsignedInteger('max_players')->default(100);
            $table->timestamp('scheduled_start_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('call_interval')->default(5);
            $table->string('winner_policy')->default('first_valid');
            $table->json('prize_configuration')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'game_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
