<?php

namespace App\Console\Commands\Updates;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Throwable;

class Update263
{
    private const VERSION = '1.9.3.4';

    public function apply(): bool
    {
        try {
            if (! Schema::hasTable('call_transcription_policy')) {
                echo "call_transcription_policy table not found; skipped recorder_summary_language column.\n";

                return true;
            }

            if (Schema::hasColumn('call_transcription_policy', 'recorder_summary_language')) {
                echo "Column recorder_summary_language already exists.\n";

                return true;
            }

            Schema::table('call_transcription_policy', function (Blueprint $table) {
                $table->string('recorder_summary_language', 32)->nullable()->after('translation_language');
            });

            echo "Added recorder_summary_language to call_transcription_policy.\n";
            echo 'Update '.self::VERSION." completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update '.self::VERSION.": {$exception->getMessage()}\n";

            return false;
        }
    }
}
