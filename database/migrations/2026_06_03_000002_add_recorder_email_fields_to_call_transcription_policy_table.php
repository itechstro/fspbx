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
            if (! Schema::hasColumn('call_transcription_policy', 'email_transcription_recorder')) {
                $table->boolean('email_transcription_recorder')->default(false)->after('email_transcription');
            }

            if (! Schema::hasColumn('call_transcription_policy', 'email_recorder')) {
                $table->string('email_recorder')->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('call_transcription_policy')) {
            return;
        }

        Schema::table('call_transcription_policy', function (Blueprint $table) {
            foreach (['email_recorder', 'email_transcription_recorder'] as $column) {
                if (Schema::hasColumn('call_transcription_policy', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
