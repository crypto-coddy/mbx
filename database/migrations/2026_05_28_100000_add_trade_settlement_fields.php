<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->timestamp('settlement_requested_at')->nullable()->after('closed_at');
            $table->foreignId('settled_by')->nullable()->after('settlement_requested_at')->constrained('users')->nullOnDelete();
            $table->text('admin_settlement_note')->nullable()->after('settled_by');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE trades MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT 'open'");
        }
    }

    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->dropConstrainedForeignId('settled_by');
            $table->dropColumn(['settlement_requested_at', 'admin_settlement_note']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE trades MODIFY COLUMN status ENUM('open', 'closed', 'cancelled') NOT NULL DEFAULT 'open'");
        }
    }
};
