<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Artisan;
use Throwable;

class Update232
{
    private const VERSION = '1.8.8.39';

    public function apply(): bool
    {
        try {
            Artisan::call('migrate', [
                '--force' => true,
                '--path' => 'database/migrations/2026_06_17_000001_add_ai_usage_metrics_to_call_transcriptions_table.php',
            ]);
            Artisan::call('migrate', [
                '--force' => true,
                '--path' => 'database/migrations/2026_06_17_000002_create_domain_usage_ledger_table.php',
            ]);
            Artisan::call('migrate', [
                '--force' => true,
                '--path' => 'database/migrations/2026_06_17_000003_create_recorder_analytics_executive_summary_runs_table.php',
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
