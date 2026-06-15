<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recorder_analytics_report_schedules', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('domain_uuid')->unique();
            $table->boolean('enabled')->default(false);
            $table->json('emails')->nullable();
            $table->string('frequency', 20)->default('weekly');
            $table->string('send_time', 5)->default('08:00');
            $table->unsignedTinyInteger('day_of_week')->nullable()->comment('0=Sunday, for weekly schedules');
            $table->unsignedTinyInteger('day_of_month')->nullable()->comment('1-28, for monthly schedules');
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->index(['enabled', 'frequency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recorder_analytics_report_schedules');
    }
};
