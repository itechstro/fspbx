<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Artisan;
use Throwable;

class Update231
{
    private const VERSION = '1.8.8.38';

    public function apply(): bool
    {
        try {
            Artisan::call('migrate', [
                '--force' => true,
                '--path' => 'database/migrations/2026_06_16_000001_add_include_executive_summary_to_recorder_analytics_report_schedules_table.php',
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
