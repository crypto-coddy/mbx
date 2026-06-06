<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique()->nullable();
            $table->string('phone', 20)->unique();
            $table->string('password');
            $table->string('referral_code', 20)->unique();
            $table->unsignedBigInteger('referred_by')->nullable();
            $table->foreign('referred_by')->references('id')->on('users')->nullOnDelete();
            $table->enum('status', ['active', 'inactive', 'suspended', 'banned'])->default('inactive');
            $table->enum('kyc_status', ['not_submitted', 'pending', 'approved', 'rejected'])->default('not_submitted');
            $table->string('kyc_rejection_reason')->nullable();
            $table->string('otp', 10)->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->boolean('phone_verified')->default(false);
            $table->boolean('email_verified_flag')->default(false);
            $table->text('fcm_token')->nullable();
            $table->softDeletes();
            $table->rememberToken();
            $table->timestamps();
            $table->index('referral_code');
            $table->index('referred_by');
            $table->index('status');
            $table->index('kyc_status');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
