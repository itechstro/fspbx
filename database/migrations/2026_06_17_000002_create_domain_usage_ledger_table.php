<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('domain_usage_ledger')) {
            return;
        }

        Schema::create('domain_usage_ledger', function (Blueprint $table) {
            $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('domain_uuid');
            $table->string('period', 7);
            $table->string('metric', 64);
            $table->decimal('amount', 16, 6)->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['domain_uuid', 'period', 'metric']);
            $table->index(['domain_uuid', 'period']);
            $table->index(['metric', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_usage_ledger');
    }
};
