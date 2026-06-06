<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 20, 8);
            $table->string('currency', 10)->default('USD');
            $table->json('bank_details');
            $table->enum('status', ['pending', 'processing', 'approved', 'rejected', 'paid'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->string('transaction_reference')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
    }
};
