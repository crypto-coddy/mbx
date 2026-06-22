<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposit_requests', function (Blueprint $table) {
            $table->string('payment_screenshot_path')->nullable()->after('note');
            $table->string('payment_screenshot_url')->nullable()->after('payment_screenshot_path');
        });
    }

    public function down(): void
    {
        Schema::table('deposit_requests', function (Blueprint $table) {
            $table->dropColumn(['payment_screenshot_path', 'payment_screenshot_url']);
        });
    }
};
