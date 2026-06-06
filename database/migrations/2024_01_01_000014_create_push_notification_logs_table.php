<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->enum('type', [
                'kyc_approved', 'kyc_rejected', 'trade_closed', 'trade_profit', 'trade_loss',
                'withdrawal_approved', 'withdrawal_rejected', 'withdrawal_paid',
                'new_referral', 'commission_earned', 'system_announcement', 'support_reply',
            ]);
            $table->string('fcm_token')->nullable();
            $table->enum('status', ['sent', 'failed', 'pending'])->default('pending');
            $table->text('error_message')->nullable();
            $table->boolean('is_broadcast')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notification_logs');
    }
};
