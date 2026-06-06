<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('user_profiles', 'mobile_chart_data_source')) {
                $table->string('mobile_chart_data_source', 16)->nullable()->after('country');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('user_profiles', 'mobile_chart_data_source')) {
                $table->dropColumn('mobile_chart_data_source');
            }
        });
    }
};
