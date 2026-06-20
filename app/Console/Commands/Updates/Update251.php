<?php

namespace App\Console\Commands\Updates;

use Throwable;

class Update251
{
    private const VERSION = '1.9.0.9';

    public function apply(): bool
    {
        try {
            echo 'CloudPLAY QR payload now follows per-domain CloudPLAY config instead of the global mobile_app_provider default.' . PHP_EOL;
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }
}
