<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recorder_analytics_report_schedules', function (Blueprint $table) {
            $table->boolean('include_executive_summary')->default(false)->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table('recorder_analytics_report_schedules', function (Blueprint $table) {
            $table->dropColumn('include_executive_summary');
        });
    }
};
