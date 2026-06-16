<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_history_analytics_report_schedules', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('domain_uuid')->unique();
            $table->boolean('enabled')->default(false);
            $table->boolean('include_executive_summary')->default(false);
            $table->json('filters')->nullable();
            $table->json('emails')->nullable();
            $table->string('frequency', 20)->default('weekly');
            $table->string('send_time', 5)->default('08:00');
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->index(['enabled', 'frequency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_history_analytics_report_schedules');
    }
};
