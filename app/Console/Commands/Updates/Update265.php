<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use Throwable;

class Update265
{
    private const VERSION = '1.9.3.6';

    public function apply(): bool
    {
        try {
            $deleted = DefaultSettings::query()
                ->where('default_setting_category', 'call_transcription')
                ->where('default_setting_subcategory', 'recorder_summary_language')
                ->delete();

            if ($deleted > 0) {
                echo "Removed call_transcription.recorder_summary_language from default settings.\n";
            } else {
                echo "call_transcription.recorder_summary_language default setting not found; nothing to remove.\n";
            }

            echo 'Update '.self::VERSION." completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update '.self::VERSION.": {$exception->getMessage()}\n";

            return false;
        }
    }
}
