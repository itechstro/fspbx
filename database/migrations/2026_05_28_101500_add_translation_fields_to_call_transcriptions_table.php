<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('call_transcriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('call_transcriptions', 'translation_external_id')) {
                $table->string('translation_external_id')->nullable();
            }

            if (!Schema::hasColumn('call_transcriptions', 'translation_status')) {
                $table->string('translation_status', 32)->nullable()->index();
            }

            if (!Schema::hasColumn('call_transcriptions', 'translation_error')) {
                $table->text('translation_error')->nullable();
            }

            if (!Schema::hasColumn('call_transcriptions', 'translation_payload')) {
                $table->jsonb('translation_payload')->nullable();
            }

            if (!Schema::hasColumn('call_transcriptions', 'translation_requested_at')) {
                $table->timestamp('translation_requested_at')->nullable();
            }

            if (!Schema::hasColumn('call_transcriptions', 'translation_completed_at')) {
                $table->timestamp('translation_completed_at')->nullable();
            }

            if (!Schema::hasColumn('call_transcriptions', 'translation_target_language')) {
                $table->string('translation_target_language', 32)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('call_transcriptions', function (Blueprint $table) {
            $table->dropColumn([
                'translation_external_id',
                'translation_status',
                'translation_error',
                'translation_payload',
                'translation_requested_at',
                'translation_completed_at',
                'translation_target_language',
            ]);
        });
    }
};
