<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Artisan;
use Throwable;

class Update236
{
    private const VERSION = '1.8.8.43';

    public function apply(): bool
    {
        try {
            Artisan::call('migrate', [
                '--path' => 'database/migrations/2026_06_16_000002_add_search_to_recorder_analytics_report_schedules_table.php',
                '--force' => true,
            ]);
            echo trim(Artisan::output()) . "\n";
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }
}
