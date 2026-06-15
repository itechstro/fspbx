<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('call_transcription_policy')) {
            return;
        }

        Schema::table('call_transcription_policy', function (Blueprint $table) {
            if (! Schema::hasColumn('call_transcription_policy', 'auto_translate_recorder')) {
                $table->boolean('auto_translate_recorder')->default(false)->after('auto_summarize_recorder');
            }

            if (! Schema::hasColumn('call_transcription_policy', 'email_translation_recorder')) {
                $table->boolean('email_translation_recorder')->default(false)->after('email_translation');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('call_transcription_policy')) {
            return;
        }

        Schema::table('call_transcription_policy', function (Blueprint $table) {
            foreach (['email_translation_recorder', 'auto_translate_recorder'] as $column) {
                if (Schema::hasColumn('call_transcription_policy', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
