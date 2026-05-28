<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_transcription_policy', function (Blueprint $table) {
            if (!Schema::hasColumn('call_transcription_policy', 'translation_language')) {
                $table->string('translation_language', 32)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('call_transcription_policy', function (Blueprint $table) {
            if (Schema::hasColumn('call_transcription_policy', 'translation_language')) {
                $table->dropColumn('translation_language');
            }
        });
    }
};

