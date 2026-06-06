<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (! Schema::hasColumn('assets', 'category')) {
                $table->string('category', 32)->default('commodities')->after('symbol');
            }
        });

        Schema::create('user_asset_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'asset_id']);
        });

        if (Schema::hasTable('assets')) {
            DB::table('assets')->where('symbol', 'XAU')->update(['category' => 'commodities']);
            DB::table('assets')->where('symbol', 'XAG')->update(['category' => 'commodities']);
            DB::table('assets')->where('symbol', 'USDT')->update(['category' => 'crypto']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_asset_favorites');

        Schema::table('assets', function (Blueprint $table) {
            if (Schema::hasColumn('assets', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};
