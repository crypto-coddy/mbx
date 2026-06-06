<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profile_asset_charts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->enum('chart_trend', ['up', 'down'])->default('up');
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('set_at')->nullable();
            $table->timestamps();

            $table->unique(['user_profile_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profile_asset_charts');
    }
};
