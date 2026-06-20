<?php

namespace App\Console\Commands\Updates;

use Throwable;

class Update253
{
    private const VERSION = '1.9.0.11';

    public function apply(): bool
    {
        try {
            echo 'CloudPLAY app passwords now always include upper, lower, digit, and special characters.' . PHP_EOL;
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }
}
