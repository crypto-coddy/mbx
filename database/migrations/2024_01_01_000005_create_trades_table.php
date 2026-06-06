<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['buy', 'sell']);
            $table->decimal('amount', 20, 8);
            $table->decimal('quantity', 20, 8)->nullable();
            $table->decimal('price_at_trade', 20, 8);
            $table->enum('price_source', ['live_api', 'admin_override'])->default('live_api');
            $table->decimal('closing_price', 20, 8)->nullable();
            $table->decimal('profit_loss', 20, 8)->default(0);
            $table->decimal('profit_loss_percent', 10, 4)->default(0);
            $table->enum('status', ['open', 'closed', 'cancelled'])->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'asset_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
