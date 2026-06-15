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
            if (! Schema::hasColumn('call_transcription_policy', 'auto_summarize')) {
                $table->boolean('auto_summarize')->default(false)->after('auto_transcribe');
            }

            if (! Schema::hasColumn('call_transcription_policy', 'auto_transcribe_recorder')) {
                $table->boolean('auto_transcribe_recorder')->default(false)->after('auto_summarize');
            }

            if (! Schema::hasColumn('call_transcription_policy', 'auto_summarize_recorder')) {
                $table->boolean('auto_summarize_recorder')->default(false)->after('auto_transcribe_recorder');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('call_transcription_policy')) {
            return;
        }

        Schema::table('call_transcription_policy', function (Blueprint $table) {
            foreach (['auto_summarize_recorder', 'auto_transcribe_recorder', 'auto_summarize'] as $column) {
                if (Schema::hasColumn('call_transcription_policy', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
