<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', [
                'trade_profit', 'trade_loss', 'referral_commission',
                'withdrawal', 'withdrawal_reversal', 'admin_credit',
                'admin_debit', 'deposit',
            ]);
            $table->decimal('amount', 20, 8);
            $table->enum('direction', ['credit', 'debit']);
            $table->decimal('balance_before', 20, 8);
            $table->decimal('balance_after', 20, 8);
            $table->nullableMorphs('referenceable');
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
