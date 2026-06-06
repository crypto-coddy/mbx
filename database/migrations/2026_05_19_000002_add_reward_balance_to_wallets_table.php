<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->decimal('reward_balance', 20, 8)->default(0)->after('locked_balance');
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn('reward_balance');
        });
    }
};
