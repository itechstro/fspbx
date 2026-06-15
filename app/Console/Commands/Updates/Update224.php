<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Artisan;
use Throwable;

class Update224
{
    private const VERSION = '1.8.8.31';

    public function apply(): bool
    {
        try {
            Artisan::call('migrate', [
                '--force' => true,
                '--path' => 'database/migrations/2026_06_03_000001_add_recorder_and_summary_fields_to_call_transcription_policy_table.php',
            ]);

            echo trim((string) Artisan::output()) . "\n";
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }
}
