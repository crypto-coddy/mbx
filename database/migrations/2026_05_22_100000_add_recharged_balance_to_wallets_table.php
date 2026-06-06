<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->decimal('recharged_balance', 20, 8)->default(0)->after('reward_balance');
            $table->decimal('withdrawal_locked', 20, 8)->default(0)->after('recharged_balance');
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn(['recharged_balance', 'withdrawal_locked']);
        });
    }
};
