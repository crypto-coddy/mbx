<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beneficiary_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('source_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('trade_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('referral_level');
            $table->decimal('trade_amount', 20, 8);
            $table->decimal('commission_rate', 8, 4);
            $table->decimal('commission_amount', 20, 8);
            $table->enum('status', ['pending', 'credited', 'cancelled'])->default('pending');
            $table->timestamp('credited_at')->nullable();
            $table->timestamps();
            $table->index(['beneficiary_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_commissions');
    }
};
