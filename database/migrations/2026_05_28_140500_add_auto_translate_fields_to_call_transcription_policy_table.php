<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_transcription_policy', function (Blueprint $table) {
            if (!Schema::hasColumn('call_transcription_policy', 'auto_translate')) {
                $table->boolean('auto_translate')->default(false)->after('auto_transcribe');
            }

            if (!Schema::hasColumn('call_transcription_policy', 'email_translation')) {
                $table->boolean('email_translation')->default(false)->after('email_transcription');
            }
        });
    }

    public function down(): void
    {
        Schema::table('call_transcription_policy', function (Blueprint $table) {
            if (Schema::hasColumn('call_transcription_policy', 'email_translation')) {
                $table->dropColumn('email_translation');
            }
            if (Schema::hasColumn('call_transcription_policy', 'auto_translate')) {
                $table->dropColumn('auto_translate');
            }
        });
    }
};

