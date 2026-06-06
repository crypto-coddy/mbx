<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('action');
            $table->string('description');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('ip_address', 50)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at');
            $table->index(['admin_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activity_logs');
    }
};
