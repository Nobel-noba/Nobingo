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
        Schema::table('game_players', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            if (! Schema::hasColumn('game_players', 'guest_identifier')) {
                $table->string('guest_identifier')->nullable()->after('user_id');
            }
        });

        Schema::table('game_cards', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            if (! Schema::hasColumn('game_cards', 'guest_identifier')) {
                $table->string('guest_identifier')->nullable()->after('user_id');
            }
        });

        Schema::table('game_winners', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        Schema::table('game_winners', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        Schema::table('game_cards', function (Blueprint $table) {
            $table->dropColumn('guest_identifier');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        Schema::table('game_players', function (Blueprint $table) {
            $table->dropColumn('guest_identifier');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
