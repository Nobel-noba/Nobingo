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
        Schema::create('game_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->index();
            $table->text('description')->nullable();
            $table->string('pattern_mode')->default('single');
            $table->unsignedInteger('required_pattern_count')->default(1);
            $table->json('allowed_pattern_ids');
            $table->string('winner_policy')->default('first_valid');
            $table->unsignedInteger('default_call_interval')->default(5);
            $table->unsignedInteger('default_min_players')->default(1);
            $table->unsignedInteger('default_max_players')->default(100);
            $table->unsignedBigInteger('default_entry_fee')->default(0);
            $table->json('default_prize_configuration')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_templates');
    }
};
