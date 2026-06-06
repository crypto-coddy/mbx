<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('public_user_id', 20)->nullable()->unique()->after('id');
        });

        DB::table('users')
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id) {
                DB::table('users')
                    ->where('id', $id)
                    ->update([
                        'public_user_id' => 'QX'.str_pad((string) $id, 8, '0', STR_PAD_LEFT),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['public_user_id']);
            $table->dropColumn('public_user_id');
        });
    }
};
