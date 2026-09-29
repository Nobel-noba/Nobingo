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
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedBigInteger('credit_balance')->default(0)->after('status');
        });

        Schema::table('games', function (Blueprint $table) {
            $table->unsignedBigInteger('total_pot')->default(0)->after('entry_fee');
            $table->unsignedBigInteger('winner_payout_total')->default(0)->after('total_pot');
            $table->unsignedBigInteger('house_gross_cut')->default(0)->after('winner_payout_total');
            $table->unsignedBigInteger('platform_fee')->default(0)->after('house_gross_cut');
            $table->unsignedBigInteger('company_net_cut')->default(0)->after('platform_fee');
            $table->decimal('winner_share_percentage', 5, 2)->nullable()->after('company_net_cut');
            $table->decimal('platform_share_percentage', 5, 2)->nullable()->after('winner_share_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn([
                'total_pot',
                'winner_payout_total',
                'house_gross_cut',
                'platform_fee',
                'company_net_cut',
                'winner_share_percentage',
                'platform_share_percentage',
            ]);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('credit_balance');
        });
    }
};
