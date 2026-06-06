<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('label')->nullable();
            $table->string('bank_name');
            $table->string('account_number');
            $table->string('ifsc', 20);
            $table->string('account_holder');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['account_number', 'ifsc']);
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_bank_accounts');
    }
};
