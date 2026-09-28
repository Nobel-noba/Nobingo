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
        Schema::create('bingo_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('card_number')->index();
            $table->string('status')->default('available')->index();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->string('card_hash', 64)->index();
            $table->timestamps();

            $table->unique(['company_id', 'card_number']);
            $table->unique(['company_id', 'card_hash']);
        });

        Schema::create('bingo_card_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bingo_card_id')->constrained('bingo_cards')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('card_hash', 64);
            $table->json('grid');
            $table->json('b_column');
            $table->json('i_column');
            $table->json('n_column');
            $table->json('g_column');
            $table->json('o_column');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['bingo_card_id', 'version_number']);
            $table->index(['card_hash']);
        });

        // Add foreign key constraint for current_version_id
        Schema::table('bingo_cards', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('bingo_card_versions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bingo_cards', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });

        Schema::dropIfExists('bingo_card_versions');
        Schema::dropIfExists('bingo_cards');
    }
};
