<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('domain_usage_limit_alerts')) {
            return;
        }

        Schema::create('domain_usage_limit_alerts', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('domain_uuid');
            $table->string('period', 7);
            $table->string('limit_key', 64);
            $table->string('alert_level', 32);
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['domain_uuid', 'period', 'limit_key', 'alert_level'], 'domain_usage_limit_alerts_unique');
            $table->index(['domain_uuid', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_usage_limit_alerts');
    }
};
