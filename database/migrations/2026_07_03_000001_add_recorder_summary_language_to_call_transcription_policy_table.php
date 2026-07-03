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
            if (! Schema::hasColumn('call_transcription_policy', 'recorder_summary_language')) {
                $table->string('recorder_summary_language', 32)->nullable()->after('translation_language');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('call_transcription_policy')) {
            return;
        }

        Schema::table('call_transcription_policy', function (Blueprint $table) {
            if (Schema::hasColumn('call_transcription_policy', 'recorder_summary_language')) {
                $table->dropColumn('recorder_summary_language');
            }
        });
    }
};
