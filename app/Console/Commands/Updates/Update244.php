<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Artisan;
use Throwable;

class Update244
{
    private const VERSION = '1.9.0.2';

    public function apply(): bool
    {
        try {
            Artisan::call('migrate', [
                '--path' => 'database/migrations/2026_06_15_000001_create_scheduled_announcements_tables.php',
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
