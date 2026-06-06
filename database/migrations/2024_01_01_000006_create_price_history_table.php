<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 20, 8);
            $table->decimal('open', 20, 8)->nullable();
            $table->decimal('high', 20, 8)->nullable();
            $table->decimal('low', 20, 8)->nullable();
            $table->decimal('close', 20, 8)->nullable();
            $table->enum('source', ['live_api', 'admin_override'])->default('live_api');
            $table->enum('interval', ['1m', '5m', '15m', '1h', '4h', '1d'])->default('1m');
            $table->timestamp('recorded_at');
            $table->index(['asset_id', 'recorded_at']);
            $table->index(['asset_id', 'interval', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_history');
    }
};
