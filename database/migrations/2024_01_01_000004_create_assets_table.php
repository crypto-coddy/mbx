<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('symbol', 20)->unique();
            $table->string('display_name', 100);
            $table->string('icon_url')->nullable();
            $table->string('currency', 10)->default('USD');
            $table->decimal('live_price', 20, 8)->default(0);
            $table->decimal('price_change_24h', 10, 4)->default(0);
            $table->timestamp('price_updated_at')->nullable();
            $table->decimal('admin_price', 20, 8)->nullable();
            $table->boolean('admin_override_active')->default(false);
            $table->foreignId('override_set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('override_set_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('trading_enabled')->default(true);
            $table->decimal('min_trade_amount', 20, 8)->default(10);
            $table->decimal('max_trade_amount', 20, 8)->default(100000);
            $table->json('api_config')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
