<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_commissions', function (Blueprint $table) {
            $table->dropForeign(['trade_id']);
        });

        Schema::table('referral_commissions', function (Blueprint $table) {
            $table->unsignedBigInteger('trade_id')->nullable()->change();
            $table->string('commission_source', 20)->default('trade')->after('source_user_id');
            $table->foreign('trade_id')->references('id')->on('trades')->cascadeOnDelete();
            $table->unique(
                ['beneficiary_user_id', 'source_user_id', 'referral_level', 'commission_source'],
                'referral_commissions_signup_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('referral_commissions', function (Blueprint $table) {
            $table->dropUnique('referral_commissions_signup_unique');
            $table->dropForeign(['trade_id']);
            $table->dropColumn('commission_source');
        });

        Schema::table('referral_commissions', function (Blueprint $table) {
            $table->unsignedBigInteger('trade_id')->nullable(false)->change();
            $table->foreign('trade_id')->references('id')->on('trades')->cascadeOnDelete();
        });
    }
};
