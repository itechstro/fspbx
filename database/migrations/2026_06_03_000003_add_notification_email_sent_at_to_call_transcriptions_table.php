<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('call_transcriptions')) {
            return;
        }

        Schema::table('call_transcriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('call_transcriptions', 'notification_email_sent_at')) {
                $table->timestamp('notification_email_sent_at')->nullable()->after('translation_completed_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('call_transcriptions')) {
            return;
        }

        Schema::table('call_transcriptions', function (Blueprint $table) {
            if (Schema::hasColumn('call_transcriptions', 'notification_email_sent_at')) {
                $table->dropColumn('notification_email_sent_at');
            }
        });
    }
};
