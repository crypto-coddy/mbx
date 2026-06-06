<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->enum('chart_trend', ['up', 'down'])->default('up')->after('price_change_24h');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('chart_trend');
        });
    }
};
